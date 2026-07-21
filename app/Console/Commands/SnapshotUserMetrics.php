<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('metrics:snapshot-users {--backfill-days=0 : Also backfill this many previous days}')]
#[Description('Record a daily snapshot of user account counts (total, active, deactivated, by role) as metrics')]
class SnapshotUserMetrics extends Command
{
    public function handle(): void
    {
        $this->snapshotFor(now()->startOfDay());

        $backfillDays = (int) $this->option('backfill-days');

        for ($i = 1; $i <= $backfillDays; $i++) {
            $this->snapshotFor(now()->subDays($i)->startOfDay());
        }

        $this->info(($backfillDays + 1).' day(s) snapshotted.');
    }

    /**
     * Record user account counts as of the end of the given day.
     *
     * Role counts assume each user's *current* role applied since their
     * `created_at`, since role assignments (`model_has_roles`) aren't
     * timestamped and historical role changes can't be reconstructed.
     */
    private function snapshotFor(CarbonImmutable $day): void
    {
        $cutoff = $day->endOfDay();

        $total = User::where('created_at', '<=', $cutoff)->count();

        $active = User::where('created_at', '<=', $cutoff)
            ->where(fn ($q) => $q->whereNull('deactivated_at')->orWhere('deactivated_at', '>', $cutoff))
            ->count();

        $admins = User::role(Role::Admin->value)->where('created_at', '<=', $cutoff)->count();
        $users = User::role(Role::User->value)->where('created_at', '<=', $cutoff)->count();

        metric('users:total')->date($day)->record($total);
        metric('users:active')->date($day)->record($active);
        metric('users:deactivated:total')->date($day)->record($total - $active);
        metric('users:role:admin')->date($day)->record($admins);
        metric('users:role:user')->date($day)->record($users);
    }
}
