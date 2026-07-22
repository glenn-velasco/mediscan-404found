<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invitations:prune')->daily();
Schedule::command('metrics:snapshot-users')->daily();
Schedule::command('professional-applications:prune')->daily();
Schedule::command('account-retrieval-requests:prune')->daily();
Schedule::command('registration-matches:expire-stale')->daily();

// Container logs already go to stdout/stderr -> Promtail -> Loki (see
// infrastructure/.env.production) - Log::critical here is what a Grafana
// alert rule should be watching for, since there's no other alerting
// channel wired up in this app yet. See docs/BACKUPS.md.
Schedule::command('backup:database')->dailyAt('02:00')->onFailure(function () {
    Log::critical('backup.database_command_failed', ['scheduled_at' => '02:00']);
});

// Runs a few hours after the backup job to give it (and the upload) time
// to finish - checks the backup actually landed, without ever needing the
// decryption key (see CheckBackupFreshness's own docblock for why a real
// restore drill can't be automated this way).
Schedule::command('backup:check-freshness')->dailyAt('06:00')->onFailure(function () {
    Log::critical('backup.freshness_check_command_failed', ['scheduled_at' => '06:00']);
});
