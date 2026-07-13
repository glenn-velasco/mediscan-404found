<?php

namespace App\Repositories\Eloquent;

use App\Enums\ProfessionalApplicationStatus;
use App\Models\ProfessionalApplication;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

/**
 * @extends BaseRepository<ProfessionalApplication>
 */
class ProfessionalApplicationRepository extends BaseRepository
{
    public function __construct(ProfessionalApplication $professionalApplication)
    {
        parent::__construct($professionalApplication);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ProfessionalApplication>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with([
            'user:id,first_name,middle_name,last_name,suffix,email',
            'reviewer:id,first_name,middle_name,last_name,suffix',
        ]);

        $status = $filters['status'] ?? null;

        if ($status === ProfessionalApplicationStatus::Denied->value) {
            $query->onlyTrashed()->where('status', ProfessionalApplicationStatus::Denied->value);
        } elseif ($status !== null) {
            $query->where('status', $status);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * The applicant's currently active (non-terminal) application, if any -
     * used to enforce the one-active-application-per-user invariant.
     */
    public function activeFor(int $userId): ?ProfessionalApplication
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->whereIn('status', [
                ProfessionalApplicationStatus::Processing->value,
                ProfessionalApplicationStatus::PendingReview->value,
            ])
            ->first();
    }

    /**
     * The applicant's most recent application regardless of status, for the
     * applicant-facing status page.
     */
    public function latestFor(int $userId): ?ProfessionalApplication
    {
        return $this->model->newQuery()
            ->withTrashed()
            ->where('user_id', $userId)
            ->latest()
            ->first();
    }

    /**
     * Stale applications eligible for pruning: auto-rejected, denied, or
     * still pending review, with no state change since the cutoff. Includes
     * trashed rows because rejection soft-deletes the application.
     *
     * @return LazyCollection<int, ProfessionalApplication>
     */
    public function stale(CarbonInterface $cutoff): LazyCollection
    {
        return $this->model->newQuery()
            ->withTrashed()
            ->whereIn('status', [
                ProfessionalApplicationStatus::PendingReview->value,
                ProfessionalApplicationStatus::Denied->value,
                ProfessionalApplicationStatus::AutoRejected->value,
            ])
            ->where('updated_at', '<=', $cutoff)
            ->lazyById();
    }

    /** @return array<string, mixed> */
    public function transform(ProfessionalApplication $application): array
    {
        return [
            'id' => $application->id,
            'applicant' => [
                'id' => $application->user->id,
                'name' => $application->user->fullname,
                'email' => $application->user->email,
            ],
            'id_type' => $application->id_type->value,
            'issuing_country' => $application->issuing_country,
            'profession' => $application->profession,
            'specialty' => $application->specialty,
            'license_number' => $application->license_number,
            'status' => $application->status->value,
            'face_match_score' => $application->face_match_score,
            'face_match_passed' => $application->face_match_passed,
            'liveness_score' => $application->liveness_score,
            'liveness_passed' => $application->liveness_passed,
            'rejection_reason' => $application->rejection_reason,
            'verification_notes' => $application->verification_notes,
            'role_granted' => $application->role_granted,
            'reviewed_by' => $application->reviewer?->fullname,
            'reviewed_at' => $application->reviewed_at?->toDateTimeString(),
            'created_at' => $application->created_at?->toDateTimeString(),
        ];
    }
}
