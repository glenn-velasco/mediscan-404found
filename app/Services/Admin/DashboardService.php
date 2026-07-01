<?php

namespace App\Services\Admin;

use App\Enums\Role;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public const STATS_CACHE_KEY = 'admin.dashboard.stats';

    public function __construct(private UserRepository $userRepository) {}

    /**
     * @return array{total: int, active: int, deactivated: int, by_role: array<string, int>}
     */
    public function stats(): array
    {
        return Cache::remember(self::STATS_CACHE_KEY, now()->addMonth(), function () {
            $total = $this->userRepository->countAll();
            $active = $this->userRepository->countActive();

            $byRole = [];
            foreach (Role::cases() as $role) {
                $byRole[$role->value] = $this->userRepository->countByRole($role->value);
            }

            return [
                'total' => $total,
                'active' => $active,
                'deactivated' => $total - $active,
                'by_role' => $byRole,
            ];
        });
    }

    public function flushCache(): void
    {
        Cache::forget(self::STATS_CACHE_KEY);
    }
}
