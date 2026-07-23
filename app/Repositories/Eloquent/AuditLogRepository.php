<?php

namespace App\Repositories\Eloquent;

use App\Enums\ReportCategory;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<AuditLog>
 */
class AuditLogRepository extends BaseRepository
{
    public function __construct(AuditLog $auditLog)
    {
        parent::__construct($auditLog);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function paginateForCategory(ReportCategory $category, int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->queryForCategory($category, $filters)->paginate($perPage);
    }

    /**
     * Users matching the search term who appear as an actor/subject in this
     * category's logs, to power the "filter by user" search field.
     *
     * @return Collection<int, User>
     */
    public function searchUsersForCategory(ReportCategory $category, string $query, int $limit = 20): Collection
    {
        $userIds = $this->queryForCategory($category, [])
            ->limit(1000)
            ->get(['actor_id', 'subject_id'])
            ->flatMap(fn (AuditLog $log) => [$log->actor_id, $log->subject_id])
            ->filter()
            ->unique();

        // `first_name`/`last_name` are `encrypted` casts, so the DB can't
        // filter on them - candidates (already narrowed to $userIds) are
        // fetched and compared in PHP after Eloquent decrypts them.
        $needle = mb_strtolower($query);

        return User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'email'])
            ->filter(fn (User $user) => str_contains(mb_strtolower($user->first_name ?? ''), $needle)
                || str_contains(mb_strtolower($user->last_name ?? ''), $needle)
                || str_contains(mb_strtolower($user->email), $needle))
            ->take($limit)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    private function queryForCategory(ReportCategory $category, array $filters): Builder
    {
        $search = $filters['search'] ?? '';
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $userId = $filters['user_id'] ?? null;

        $query = $this->model->newQuery()
            ->with(['actor:id,first_name,middle_name,last_name,suffix,email', 'subject:id,first_name,middle_name,last_name,suffix,email'])
            ->where(function (Builder $query) use ($category) {
                foreach ($category->actionPrefixes() as $prefix) {
                    $query->orWhere('action', 'like', $prefix.'.%');
                }
            });

        if ($search !== '') {
            $query->where('action', 'like', '%'.$search.'%');
        }

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($userId) {
            $query->where(function (Builder $query) use ($userId) {
                $query->where('actor_id', $userId)->orWhere('subject_id', $userId);
            });
        }

        return $query->latest('created_at');
    }
}
