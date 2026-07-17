<?php

namespace App\Services\Medical;

use App\Enums\AuditLogType;
use App\Events\MedicalInformationUpdated;
use App\Models\MedicalInformation;
use App\Models\User;
use App\Repositories\Eloquent\MedicalInformationRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MedicalInformationService
{
    private const CACHE_TTL_HOURS = 6;

    public function __construct(
        private MedicalInformationRepository $repository,
        private UserRepository $userRepository,
        private AuditLogger $auditLogger,
    ) {}

    public function find(int $id, ?User $actor = null): MedicalInformation
    {
        $medicalInformation = Cache::remember(
            $this->cacheKey($id),
            now()->addHours(self::CACHE_TTL_HOURS),
            fn () => $this->repository->findOrFail($id)
        );

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
     * never a decision by itself.
     *
     * @param  array{first_name: string, middle_name: ?string, last_name: string, suffix: ?string}  $nameFields
     */
    public function findLinkCandidate(array $nameFields, string $dob): ?MedicalInformation
    {
        return $this->repository->findMatchingByName($nameFields, $dob);
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
     * Sets/replaces the avatar and fans the resulting path out to every
     * currently-linked user's `profile_photo_path`, since one medical
     * record can back many accounts.
     */
    public function syncAvatar(MedicalInformation $medicalInformation, string $avatarPath, User $actor): MedicalInformation
    {
        return DB::transaction(function () use ($medicalInformation, $avatarPath, $actor) {
            $medicalInformation->update(['avatar_path' => $avatarPath]);

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
        return collect($data)->only([
            'first_name', 'middle_name', 'last_name', 'suffix',
            'dob', 'gender', 'blood_type', 'religion', 'national_id',
            'address',
            'no_blood_transfusion',
        ])->all();
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
        Cache::forget($this->cacheKey($medicalInformation->id));

        $linkedUserIds ??= $medicalInformation->users()->pluck('id')->all();

        foreach ($linkedUserIds as $userId) {
            Cache::forget($this->byUserCacheKey($userId));
        }
    }

    private function cacheKey(int $id): string
    {
        return "medical_information.{$id}";
    }

    private function byUserCacheKey(int $userId): string
    {
        return "medical_information.by_user.{$userId}";
    }
}
