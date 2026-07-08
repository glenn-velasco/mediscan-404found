<?php

namespace App\Console\Commands;

use App\Services\Kyc\ProfessionalApplicationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('professional-applications:prune {--days=5 : Prune applications with no state change for this many days}')]
#[Description('Permanently delete stale auto-rejected, denied, and pending-review professional applications and their uploaded files')]
class PruneProfessionalApplications extends Command
{
    public function handle(ProfessionalApplicationService $service): void
    {
        $count = $service->prune((int) $this->option('days'));

        $this->info("{$count} professional application(s) pruned.");
    }
}
