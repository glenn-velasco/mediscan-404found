<?php

namespace App\Console\Commands;

use App\Enums\ProfessionalApplicationStatus;
use App\Jobs\ProcessProfessionalApplication;
use App\Models\ProfessionalApplication;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('professional-applications:reprocess {id? : Reprocess a single application by id} {--missing-storage : Reprocess every pending-review application whose notes mention a storage failure}')]
#[Description('Re-dispatch KYC verification for professional applications stuck in pending review, e.g. after a storage outage is resolved')]
class ReprocessProfessionalApplication extends Command
{
    public function handle(): int
    {
        $id = $this->argument('id');

        if ($id !== null) {
            ProcessProfessionalApplication::dispatch((int) $id);
            $this->info("Application {$id} re-dispatched for processing.");

            return self::SUCCESS;
        }

        if (! $this->option('missing-storage')) {
            $this->error('Pass an application id or --missing-storage.');

            return self::FAILURE;
        }

        $applications = ProfessionalApplication::query()
            ->where('status', ProfessionalApplicationStatus::PendingReview->value)
            ->where('verification_notes', 'like', '%missing from storage%')
            ->get();

        foreach ($applications as $application) {
            ProcessProfessionalApplication::dispatch($application->id);
        }

        $this->info("{$applications->count()} application(s) re-dispatched for processing.");

        return self::SUCCESS;
    }
}
