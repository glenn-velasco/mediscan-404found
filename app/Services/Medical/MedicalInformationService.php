<?php

namespace App\Services\Medical;

use App\Enums\AuditLogType;
use App\Events\MedicalInformationUpdated;
use App\Exceptions\MedicalInformationAvatarUploadFailedException;
use App\Models\MedicalInformation;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Repositories\Eloquent\MedicalInformationRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class MedicalInformationService
{
    private const CACHE_TTL_HOURS = 6;

    // Mirrors ProfessionalApplicationService's downscale/quality settings
    // for the same reason: file size should depend on these constants, not
    // on the client's camera resolution.
    private const AVATAR_MAX_IMAGE_DIMENSION = 1600;

    private const AVATAR_IMAGE_QUALITY = 90;

    public function __construct(
        private MedicalInformationRepository $repository,
        private UserRepository $userRepository,
        private AuditLogger $auditLogger,
    ) {}

    public function find(int $id, ?User $actor = null): MedicalInformation
    {
        // Not cached: findOrFail() eager-loads relations, so caching its
        // result would mean serializing the full model + relation +
        // enum-cast object graph into Redis via PHP's native serialize().
        // That's fragile across any drift in the app's class graph between
        // the write and the read (a deploy, a container restart) and
        // previously surfaced as "incomplete object" 500s on unserialize().
        // This isn't a hot path, so correctness wins over a 6h cache here.
        $medicalInformation = $this->repository->findOrFail($id);

        $this->auditLogger->log(
            action: 'medical_information.viewed',
            type: AuditLogType::View,
            actor: $actor,
            subject: $medicalInformation->users->first(),
            metadata: ['medical_information_id' => $id],
            channel: 'api',
        );

        return $medicalInformation;
    }

    public function findForUser(int $userId): ?MedicalInformation
    {
        $id = Cache::remember(
            $this->byUserCacheKey($userId),
            now()->addHours(self::CACHE_TTL_HOURS),
            fn () => $this->userRepository->findMedicalInformationId($userId)
        );

        return $id ? $this->find($id) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): MedicalInformation
    {
        return DB::transaction(function () use ($data, $actor) {
            $medicalInformation = $this->repository->create($this->onlyFields($data));

            $this->syncContacts($medicalInformation, $data['contacts'] ?? []);
            $this->syncTransfusionConsents($medicalInformation, $data['transfusion_consents'] ?? []);

            $this->auditLogger->log(
                action: 'medical_information.created',
                type: AuditLogType::Create,
                actor: $actor,
                subject: $actor,
                metadata: ['medical_information_id' => $medicalInformation->id, 'matched' => false],
                channel: 'api',
            );

            return $medicalInformation->fresh(['contacts', 'transfusionConsents']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MedicalInformation $medicalInformation, array $data, User $actor): MedicalInformation
    {
        return DB::transaction(function () use ($medicalInformation, $data, $actor) {
            $this->repository->update($medicalInformation, $this->onlyFields($data));

            if (array_key_exists('contacts', $data)) {
                $this->syncContacts($medicalInformation, $data['contacts']);
            }

            if (array_key_exists('transfusion_consents', $data)) {
                $this->syncTransfusionConsents($medicalInformation, $data['transfusion_consents']);
            }

            $updated = $medicalInformation->fresh(['contacts', 'transfusionConsents', 'users']);

            $this->flushCache($updated);

            $this->auditLogger->log(
                action: 'medical_information.updated',
                type: AuditLogType::Update,
                actor: $actor,
                subject: $updated->users->first(),
                metadata: ['medical_information_id' => $updated->id],
                channel: 'api',
            );

            $this->broadcastUpdated($updated);

            return $updated;
        });
    }

    public function delete(MedicalInformation $medicalInformation, User $actor): void
    {
        $id = $medicalInformation->id;
        $linkedUserIds = $medicalInformation->users()->pluck('id')->all();

        DB::transaction(function () use ($medicalInformation) {
            $medicalInformation->delete();
        });

        $this->flushCache($medicalInformation, $linkedUserIds);

        $this->auditLogger->log(
            action: 'medical_information.deleted',
            type: AuditLogType::Delete,
            actor: $actor,
            metadata: ['medical_information_id' => $id],
            channel: 'api',
        );
    }

    /**
     * Used by registration: always creates a fresh record for the new
     * account immediately (registration must never block on, or reveal the
     * outcome of, a name/dob match). The registering user becomes this
     * record's primary user - primary is simply the first user ever
     * successfully linked to a record, no admin toggle involved.
     *
     * @param  array{first_name: string, middle_name: ?string, last_name: string, suffix: ?string}  $nameFields
     */
    public function createInterim(User $user, array $nameFields, string $dob, string $gender): MedicalInformation
    {
        $created = $this->repository->create([
            ...$nameFields,
            'dob' => $dob,
            'gender' => $gender,
            'primary_user_id' => $user->id,
        ]);

        $this->auditLogger->log(
            action: 'medical_information.created',
            type: AuditLogType::Create,
            actor: $user,
            subject: $user,
            metadata: ['medical_information_id' => $created->id],
            channel: 'api',
        );

        return $created;
    }

    /**
     * Finds a candidate record for matching flows (registration matches,
     * account retrieval) - name+dob (+national_id when present) match only,
     * never a decision by itself. Pass the caller's own current
     * medical_information_id as $excludeId so their own record (e.g. the
     * interim one just created at registration) never counts as a second,
     * ambiguity-causing match against itself.
     *
     * @param  array{first_name: string, middle_name: ?string, last_name: string, suffix: ?string}  $nameFields
     */
    public function findLinkCandidate(array $nameFields, string $dob, ?int $excludeId = null): ?MedicalInformation
    {
        return $this->repository->findMatchingByName($nameFields, $dob, $excludeId);
    }

    /**
     * Repoints a user's account onto an existing candidate record and
     * discards their now-unused interim record. The only place
     * `users.medical_information_id` is repointed after initial creation.
     * Shared by the registration-match accept flow and the account
     * retrieval approve flow - both end in exactly this operation, they
     * differ only in who triggers it and what gets audit-logged around it.
     */
    public function repointUserToRecord(User $user, MedicalInformation $candidate): void
    {
        DB::transaction(function () use ($user, $candidate) {
            $interimId = $user->medical_information_id;

            $user->forceFill(['medical_information_id' => $candidate->id])->save();

            if ($interimId && $interimId !== $candidate->id) {
                $this->mergeChildRecords($interimId, $candidate->id);
                MedicalInformation::query()->whereKey($interimId)->delete();
            }

            if ($candidate->primary_user_id === null) {
                $candidate->forceFill(['primary_user_id' => $user->id])->save();
            }

            $linkedUserIds = $candidate->users()->pluck('id')->all();

            $this->flushCache($candidate, $linkedUserIds);
            $this->flushCache($candidate, [$user->id]);

            $this->broadcastUpdated($candidate, array_values(array_unique([...$linkedUserIds, $user->id])));
        });
    }

    /**
     * Moves an interim record's patient-authored child rows onto the
     * candidate record before the interim record itself gets deleted -
     * otherwise its cascadeOnDelete children (allergies, diagnoses,
     * medications, emergency contacts, plain contacts, transfusion
     * consents) would simply vanish instead of merging into the shared
     * record. Raw query-builder updates: only the plain-int FK column
     * changes, no encrypted columns involved. `is_primary` is demoted on
     * moved contacts/emergency-contacts so the candidate record never ends
     * up with two rows both claiming to be primary.
     */
    private function mergeChildRecords(int $fromId, int $toId): void
    {
        $now = now();

        foreach (['allergies', 'diagnoses', 'medications'] as $table) {
            DB::table($table)->where('medical_information_id', $fromId)
                ->update(['medical_information_id' => $toId, 'updated_at' => $now]);
        }

        foreach (['emergency_contacts', 'medical_information_contacts'] as $table) {
            DB::table($table)->where('medical_information_id', $fromId)
                ->update(['medical_information_id' => $toId, 'is_primary' => false, 'updated_at' => $now]);
        }

        DB::table('medical_information_transfusion_consents')->where('medical_information_id', $fromId)
            ->update(['medical_information_id' => $toId, 'updated_at' => $now]);
    }

    /**
     * Used by the registration-match accept flow when the requester was
     * held as a PendingRegistration rather than given an account up front -
     * unlike repointUserToRecord(), there's no interim record to merge or
     * discard, since none was ever created. Creates the User directly with
     * medical_information_id already pointing at the candidate. Role
     * assignment, audit logging, and the signup metric are the caller's
     * responsibility (mirrors how createInterim() callers handle those
     * separately too).
     */
    public function materializeUserOntoRecord(PendingRegistration $pendingRegistration, MedicalInformation $candidate): User
    {
        return DB::transaction(function () use ($pendingRegistration, $candidate) {
            $user = User::create([
                'first_name' => $pendingRegistration->first_name,
                'middle_name' => $pendingRegistration->middle_name,
                'last_name' => $pendingRegistration->last_name,
                'suffix' => $pendingRegistration->suffix,
                'dob' => $pendingRegistration->dob,
                'gender' => $pendingRegistration->gender,
                'address' => $pendingRegistration->address,
                'phone_number' => $pendingRegistration->phone_number,
                'phone_country_code' => $pendingRegistration->phone_country_code,
                'email' => $pendingRegistration->email,
                'password' => $pendingRegistration->password,
            ]);

            // medical_information_id isn't in User's Fillable list (by design - it's only ever
            // set programmatically after account creation, never via mass-assignable input).
            $user->forceFill(['medical_information_id' => $candidate->id])->save();

            $linkedUserIds = $candidate->users()->pluck('id')->all();
            $this->flushCache($candidate, $linkedUserIds);
            $this->broadcastUpdated($candidate, $linkedUserIds);

            return $user;
        });
    }

    /**
     * Stores an avatar photo picked from the gallery or taken as a plain
     * selfie - no face verification, just an upload.
     */
    public function updateAvatar(MedicalInformation $medicalInformation, UploadedFile $avatar, User $actor): MedicalInformation
    {
        $avatarPath = $this->storeAvatarImage($medicalInformation, $avatar);

        return $this->syncAvatar($medicalInformation, $avatarPath, $actor);
    }

    /**
     * Downscales/re-encodes the capture as JPEG and stores it on the public
     * disk, mirroring ProfessionalApplicationService::storeResizedImageOrFail().
     */
    private function storeAvatarImage(MedicalInformation $medicalInformation, UploadedFile $frame): string
    {
        $path = 'avatars/medical-information/'.$medicalInformation->id.'-'.Str::random(12).'.jpg';

        $encoded = (new ImageManager(new Driver))
            ->decodePath($frame->getRealPath())
            ->scaleDown(width: self::AVATAR_MAX_IMAGE_DIMENSION, height: self::AVATAR_MAX_IMAGE_DIMENSION)
            ->encode(new JpegEncoder(quality: self::AVATAR_IMAGE_QUALITY));

        if (! Storage::disk('s3')->put($path, (string) $encoded)) {
            throw new MedicalInformationAvatarUploadFailedException;
        }

        Log::info('Avatar stored to S3', [
            'path' => $path,
            'url' => Storage::disk('s3')->url($path),
            'exists' => Storage::disk('s3')->exists($path),
        ]);

        return $path;
    }

    /**
     * Sets/replaces the avatar and fans the resulting path out to every
     * currently-linked user's `profile_photo_path`, since one medical
     * record can back many accounts.
     */
    public function syncAvatar(MedicalInformation $medicalInformation, string $avatarPath, User $actor): MedicalInformation
    {
        return DB::transaction(function () use ($medicalInformation, $avatarPath, $actor) {
            $medicalInformation->update(['avatar_path' => $avatarPath]);

            Log::info('Avatar synced to medical information', [
                'medical_information_id' => $medicalInformation->id,
                'avatar_path' => $avatarPath,
                'avatar_url' => $medicalInformation->fresh()->avatar,
                's3_url' => Storage::disk('s3')->url($avatarPath),
            ]);

            $linkedUsers = $medicalInformation->users;
            foreach ($linkedUsers as $linkedUser) {
                $linkedUser->forceFill(['profile_photo_path' => $avatarPath])->save();
            }

            $this->flushCache($medicalInformation, $linkedUsers->pluck('id')->all());

            $this->auditLogger->log(
                action: 'medical_information.avatar_updated',
                type: AuditLogType::Update,
                actor: $actor,
                subject: $actor,
                metadata: ['medical_information_id' => $medicalInformation->id],
                channel: 'api',
            );

            $this->broadcastUpdated($medicalInformation, $linkedUsers->pluck('id')->all());

            return $medicalInformation;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     */
    private function syncContacts(MedicalInformation $medicalInformation, array $contacts): void
    {
        $medicalInformation->contacts()->delete();

        foreach ($contacts as $contact) {
            $medicalInformation->contacts()->create($contact);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $consents
     */
    private function syncTransfusionConsents(MedicalInformation $medicalInformation, array $consents): void
    {
        $medicalInformation->transfusionConsents()->delete();

        foreach ($consents as $consent) {
            $medicalInformation->transfusionConsents()->create($consent);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function onlyFields(array $data): array
    {
        $fields = collect($data)->only([
            'first_name', 'middle_name', 'last_name', 'suffix',
            'gender', 'blood_type', 'religion', 'national_id',
            'address',
            'no_blood_transfusion',
        ])->all();

        if (array_key_exists('date_of_birth', $data)) {
            $fields['dob'] = $data['date_of_birth'];
        }

        return $fields;
    }

    /**
     * @param  array<int, int>|null  $linkedUserIds
     */
    private function broadcastUpdated(MedicalInformation $medicalInformation, ?array $linkedUserIds = null): void
    {
        $linkedUserIds ??= $medicalInformation->users()->pluck('id')->all();

        if ($linkedUserIds !== []) {
            MedicalInformationUpdated::dispatch($medicalInformation->id, $linkedUserIds);
        }
    }

    /**
     * @param  array<int, int>|null  $linkedUserIds
     */
    private function flushCache(MedicalInformation $medicalInformation, ?array $linkedUserIds = null): void
    {
        $linkedUserIds ??= $medicalInformation->users()->pluck('id')->all();

        foreach ($linkedUserIds as $userId) {
            Cache::forget($this->byUserCacheKey($userId));
        }
    }

    private function byUserCacheKey(int $userId): string
    {
        return "medical_information.by_user.{$userId}";
    }
}
