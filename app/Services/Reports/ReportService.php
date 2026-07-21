<?php

namespace App\Services\Reports;

use App\Enums\ReportCategory;
use App\Models\AuditLog;
use App\Models\User;
use App\Repositories\Eloquent\AuditLogRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(private AuditLogRepository $auditLogRepository) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function paginate(ReportCategory $category, int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->auditLogRepository->paginateForCategory($category, $perPage, $filters);
    }

    /** @return Collection<int, User> */
    public function searchUsersForCategory(ReportCategory $category, string $query): Collection
    {
        return $this->auditLogRepository->searchUsersForCategory($category, $query);
    }

    /** @return array<string, mixed> */
    public function transform(AuditLog $auditLog): array
    {
        return [
            'id' => $auditLog->id,
            'action' => $auditLog->action,
            'description' => $auditLog->description,
            'type' => $auditLog->type?->value,
            'actor' => $auditLog->actor ? [
                'id' => $auditLog->actor->id,
                'name' => $auditLog->actor->fullname,
                'email' => $auditLog->actor->email,
            ] : null,
            'subject' => $auditLog->subject ? [
                'id' => $auditLog->subject->id,
                'name' => $auditLog->subject->fullname,
                'email' => $auditLog->subject->email,
            ] : null,
            'channel' => $auditLog->channel,
            'metadata' => $auditLog->metadata,
            'created_at' => $auditLog->created_at->toIso8601String(),
        ];
    }
}
