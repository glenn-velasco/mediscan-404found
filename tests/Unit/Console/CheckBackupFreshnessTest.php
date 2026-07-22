<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('backups');
    Config::set('backup.gpg_recipient', 'backups@example.com');
    Config::set('backup.disk', 'backups');
});

it('skips cleanly when backups are not configured', function () {
    Config::set('backup.gpg_recipient', null);
    Log::shouldReceive('critical')->never();

    $this->artisan('backup:check-freshness')->assertExitCode(0);
});

it('fails and logs critical when no backup files exist at all', function () {
    Log::shouldReceive('critical')->once()->with('backup.freshness_check_failed', Mockery::on(
        fn ($context) => $context['reason'] === 'no backup files found on disk at all'
    ));

    $this->artisan('backup:check-freshness')->assertExitCode(1);
});

it('fails and logs critical when the latest backup is too old', function () {
    $stale = now()->subHours(30)->format('Y-m-d_His');
    Storage::disk('backups')->put("database/{$stale}.dump.gpg", str_repeat('x', 2048));

    Log::shouldReceive('critical')->once()->with('backup.freshness_check_failed', Mockery::on(
        fn ($context) => $context['reason'] === 'latest backup is older than expected'
    ));

    $this->artisan('backup:check-freshness')->assertExitCode(1);
});

it('fails and logs critical when the latest backup is suspiciously small', function () {
    $recent = now()->subHours(2)->format('Y-m-d_His');
    Storage::disk('backups')->put("database/{$recent}.dump.gpg", 'tiny');

    Log::shouldReceive('critical')->once()->with('backup.freshness_check_failed', Mockery::on(
        fn ($context) => $context['reason'] === 'latest backup is suspiciously small - likely a failed/truncated dump or encrypt step'
    ));

    $this->artisan('backup:check-freshness')->assertExitCode(1);
});

it('succeeds when a recent, non-empty backup exists', function () {
    $recent = now()->subHours(2)->format('Y-m-d_His');
    Storage::disk('backups')->put("database/{$recent}.dump.gpg", str_repeat('x', 2048));

    Log::shouldReceive('critical')->never();

    $this->artisan('backup:check-freshness')->assertExitCode(0);
});
