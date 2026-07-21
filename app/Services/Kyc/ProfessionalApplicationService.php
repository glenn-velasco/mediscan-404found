<?php

namespace App\Services\Kyc;

use App\Enums\AuditLogType;
use App\Enums\IdType;
use App\Enums\Permission;
use App\Enums\WorkflowStatus;
use App\Events\ProfessionalApplicationStatusChanged;
use App\Exceptions\ProfessionalApplicationAlreadyPendingException;
use App\Exceptions\ProfessionalApplicationAlreadyReviewedException;
use App\Exceptions\ProfessionalApplicationUploadFailedException;
use App\Jobs\ProcessProfessionalApplication;
use App\Models\ProfessionalApplication;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Eloquent\ProfessionalApplicationRepository;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class ProfessionalApplicationService
{
    private const DISK = 's3';

    private const ONE_ACTIVE_PER_USER_INDEX = 'professional_applications_one_active_per_user';

    // Native camera captures can run tens of megapixels; the OCR/face-match
    // sidecar doesn't need that detail, and the web app's own canvas-based
    // liveness capture already tops out well below this, so downscaling
    // mobile uploads to match keeps upload size independent of camera
    // quality without hurting verification accuracy.
    private const MAX_IMAGE_DIMENSION = 1600;

    private const IMAGE_QUALITY = 90;

    public function __construct(
        private ProfessionalApplicationRepository $professionalApplicationRepository,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->professionalApplicationRepository->paginate($perPage, $filters)
            ->through($this->professionalApplicationRepository->transform(...));
    }

    public function ensureOwnedBy(ProfessionalApplication $application, User $user): void
    {
        abort_unless($application->user_id === $user->id, 404);
    }

    public function latestFor(User $user): ?ProfessionalApplication
    {
        return $this->professionalApplicationRepository->latestFor($user->id);
    }

    /** @return array<string, mixed> */
    public function transform(ProfessionalApplication $application): array
    {
        return $this->professionalApplicationRepository->transform($application);
    }

    /**
     * @param  array<string, mixed>  $data  keys: id_type (string), id_photo (UploadedFile), selfie_frames (UploadedFile[])
     */
    public function submit(User $user, array $data): ProfessionalApplication
    {
        if ($this->professionalApplicationRepository->activeFor($user->id)) {
            throw new ProfessionalApplicationAlreadyPendingException;
        }

        $idType = IdType::from($data['id_type']);
        $folder = 'professional-applications/'.$user->id.'/'.Str::uuid();

        try {
            $application = $this->insertApplication($user, $data, $idType, $folder);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), self::ONE_ACTIVE_PER_USER_INDEX)) {
                throw new ProfessionalApplicationAlreadyPendingException;
            }

            throw $e;
        }

        $this->broadcastStatusChanged($application);

        return $application;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * File uploads happen here, outside any DB transaction - they're
     * independent network I/O to S3 with nothing to roll back, so there's no
     * reason to hold a DB connection/transaction open for their duration.
     * Only the row insert (and the afterCommit job dispatch) needs one.
     */
    private function insertApplication(User $user, array $data, IdType $idType, string $folder): ProfessionalApplication
    {
        $idPhotoPath = $this->storeOrFail($data['id_photo'], $folder);

        /** @var array<int, UploadedFile> $selfieFrames */
        $selfieFrames = array_values($data['selfie_frames']);
        $frames = collect($selfieFrames)
            ->map(fn (UploadedFile $frame, int $index) => $this->storeOrFail($frame, $folder, "selfie-frame-{$index}.jpg"));

        /** @var array<int, UploadedFile> $flashFrames */
        $flashFrames = array_values($data['flash_frames']);
        /** @var array<int, string> $flashColors */
        $flashColors = array_values($data['flash_colors']);
        $livenessFlashFrames = collect($flashFrames)
            ->map(fn (UploadedFile $frame, int $index) => [
                'path' => $this->storeOrFail($frame, $folder, "flash-frame-{$index}.jpg"),
                'color' => $flashColors[$index],
            ])
            ->all();

        return DB::transaction(function () use ($user, $idType, $idPhotoPath, $frames, $livenessFlashFrames) {
            $application = $this->professionalApplicationRepository->create([
                'user_id' => $user->id,
                'id_type' => $idType->value,
                'issuing_country' => $idType->issuingCountry(),
                'id_photo_path' => $idPhotoPath,
                // The last captured frame is most likely to be past the
                // blink prompt, so it doubles as the canonical face-match
                // image and (on approval) the profile photo.
                'selfie_path' => $frames->last(),
                'selfie_frame_paths' => $frames->all(),
                'liveness_flash_frames' => $livenessFlashFrames,
                'status' => WorkflowStatus::Processing->value,
            ]);

            ProcessProfessionalApplication::dispatch($application->id)->afterCommit();

            return $application;
        });
    }

    private function storeOrFail(UploadedFile $file, string $folder, ?string $name = null): string
    {
        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            return $this->storeResizedImageOrFail($file, $folder, $name);
        }

        $path = $name === null ? $file->store($folder, self::DISK) : $file->storeAs($folder, $name, self::DISK);

        if ($path === false) {
            throw new ProfessionalApplicationUploadFailedException;
        }

        return $path;
    }

    /**
     * Downscales and re-encodes the upload as JPEG before storing, so file
     * size depends on MAX_IMAGE_DIMENSION/IMAGE_QUALITY rather than the
     * client's camera resolution.
     */
    private function storeResizedImageOrFail(UploadedFile $file, string $folder, ?string $name): string
    {
        $path = $folder.'/'.($name ?? Str::random(40).'.jpg');

        $encoded = (new ImageManager(new Driver))
            ->decodePath($file->getRealPath())
            ->scaleDown(width: self::MAX_IMAGE_DIMENSION, height: self::MAX_IMAGE_DIMENSION)
            ->encode(new JpegEncoder(quality: self::IMAGE_QUALITY));

        if (! Storage::disk(self::DISK)->put($path, (string) $encoded)) {
            throw new ProfessionalApplicationUploadFailedException;
        }

        return $path;
    }

    public function approve(ProfessionalApplication $application, User $admin): void
    {
        if ($application->isTerminal()) {
            throw new ProfessionalApplicationAlreadyReviewedException;
        }

        DB::transaction(function () use ($application, $admin) {
            $roleName = Str::slug($application->profession ?? 'verified-professional');

            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo(Permission::VerifiedProfessional->value);

            $application->user->assignRole($role);

            $publicAvatarPath = 'avatars/'.$application->user_id.'.jpg';
            Storage::disk('public')->put(
                $publicAvatarPath,
                Storage::disk(self::DISK)->get($application->selfie_path)
            );

            $application->user->forceFill(['profile_photo_path' => $publicAvatarPath])->save();

            $application->forceFill([
                'status' => WorkflowStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'role_granted' => $roleName,
            ])->save();
        });

        $this->auditLogger->log(
            action: 'professional_application.approved',
            type: AuditLogType::Accepted,
            actor: $admin,
            subject: $application->user,
            metadata: ['professional_application_id' => $application->id],
            channel: 'web',
        );

        $this->broadcastStatusChanged($application);
    }

    public function reject(ProfessionalApplication $application, User $admin, string $reason): void
    {
        if ($application->isTerminal()) {
            throw new ProfessionalApplicationAlreadyReviewedException;
        }

        $applicationId = $application->id;
        $applicant = $application->user;

        DB::transaction(function () use ($application, $admin, $reason) {
            $application->forceFill([
                'status' => WorkflowStatus::Denied,
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            $application->delete();
        });

        $this->auditLogger->log(
            action: 'professional_application.rejected',
            type: AuditLogType::Denied,
            actor: $admin,
            subject: $applicant,
            metadata: ['professional_application_id' => $applicationId, 'reason' => $reason],
            channel: 'web',
        );

        $this->broadcastStatusChanged($application);
    }

    /**
     * Permanently remove stale auto-rejected, denied, and pending-review
     * applications (default: no state change for 5 days), including their
     * uploaded KYC files. All of an application's files live in one
     * per-application folder, so deleting the id photo's directory removes
     * the selfie frames and flash frames with it.
     */
    public function prune(int $days = 5): int
    {
        $count = 0;

        $this->professionalApplicationRepository->stale(now()->subDays($days))
            ->each(function (ProfessionalApplication $application) use (&$count) {
                Storage::disk(self::DISK)->deleteDirectory(dirname($application->id_photo_path));

                $application->forceDelete();

                $count++;
            });

        return $count;
    }

    private function broadcastStatusChanged(ProfessionalApplication $application): void
    {
        event(new ProfessionalApplicationStatusChanged($application->id, $application->user_id, $application->status->value));
    }
}
