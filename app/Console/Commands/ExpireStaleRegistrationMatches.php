<?php

namespace App\Console\Commands;

use App\Services\Medical\MedicalInformationRegistrationMatchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('registration-matches:expire-stale {--days=7 : Expire matches still pending after this many days}')]
#[Description('Auto-resolves registration matches nobody responded to, blocking the requester\'s account the same way an explicit deny would')]
class ExpireStaleRegistrationMatches extends Command
{
    public function handle(MedicalInformationRegistrationMatchService $service): void
    {
        $count = $service->expireStale((int) $this->option('days'));

        $this->info("{$count} registration match(es) expired.");
    }
}
