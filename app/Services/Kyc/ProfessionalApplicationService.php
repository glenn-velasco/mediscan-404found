<?php

namespace App\Services\Kyc;

use App\Enums\IdType;
use App\Enums\Permission;
use App\Enums\ProfessionalApplicationStatus;
use App\Events\ProfessionalApplicationStatusChanged;
use App\Exceptions\ProfessionalApplicationAlreadyPendingException;
use App\Jobs\ProcessProfessionalApplication;
use App\Models\ProfessionalApplication;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Eloquent\ProfessionalApplicationRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfessionalApplicationService
{
    private const DISK = 's3';

    public function __construct(private ProfessionalApplicationRepository $professionalApplicationRepository) {}

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
     * @param  array<string, mixed>  $data  keys: id_type (string), id_photo/coe (UploadedFile), selfie_frames (UploadedFile[])
     */
    public function submit(User $user, array $data): ProfessionalApplication
    {
        if ($this->professionalApplicationRepository->activeFor($user->id)) {
            throw new ProfessionalApplicationAlreadyPendingException;
        }

        $idType = IdType::from($data['id_type']);
        $folder = 'professional-applications/'.$user->id.'/'.Str::uuid();

        $application = DB::transaction(function () use ($user, $data, $idType, $folder) {
            $idPhotoPath = $data['id_photo']->store($folder, self::DISK);
            $coePath = $data['coe']->store($folder, self::DISK);

            /** @var array<int, UploadedFile> $selfieFrames */
            $selfieFrames = array_values($data['selfie_frames']);
            $frames = collect($selfieFrames)
                ->map(fn (UploadedFile $frame, int $index) => $frame->storeAs($folder, "selfie-frame-{$index}.jpg", self::DISK));

            /** @var array<int, UploadedFile> $flashFrames */
            $flashFrames = array_values($data['flash_frames']);
            /** @var array<int, string> $flashColors */
            $flashColors = array_values($data['flash_colors']);
            $livenessFlashFrames = collect($flashFrames)
                ->map(fn (UploadedFile $frame, int $index) => [
                    'path' => $frame->storeAs($folder, "flash-frame-{$index}.jpg", self::DISK),
                    'color' => $flashColors[$index],
                ])
                ->all();

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
                'coe_path' => $coePath,
                'coe_original_filename' => $data['coe']->getClientOriginalName(),
                'status' => ProfessionalApplicationStatus::Processing->value,
            ]);

            ProcessProfessionalApplication::dispatch($application->id)->afterCommit();

            return $application;
        });

        event(new ProfessionalApplicationStatusChanged($application));

        return $application;
    }

    public function approve(ProfessionalApplication $application, User $admin): void
    {
        DB::transaction(function () use ($application, $admin) {
            $roleName = Str::slug($application->specialty ?? $application->profession ?? 'verified-professional');

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
                'status' => ProfessionalApplicationStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'role_granted' => $roleName,
            ])->save();
        });

        event(new ProfessionalApplicationStatusChanged($application));
    }

    public function reject(ProfessionalApplication $application, User $admin, string $reason): void
    {
        DB::transaction(function () use ($application, $admin, $reason) {
            $application->forceFill([
                'status' => ProfessionalApplicationStatus::Denied,
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            $application->delete();
        });

        event(new ProfessionalApplicationStatusChanged($application));
    }
}
