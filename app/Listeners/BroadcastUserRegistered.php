<?php

namespace App\Listeners;

use App\Services\Admin\DashboardService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Broadcast;

class BroadcastUserRegistered implements ShouldQueueAfterCommit
{
    public function __construct(private DashboardService $adminDashboardService) {}

    public function handle(Registered $event): void
    {
        $this->adminDashboardService->flushCache();

        Broadcast::private('admin-dashboard')
            ->as('UserRegistered')
            ->with(['stats' => $this->adminDashboardService->stats()])
            ->send();
    }
}
