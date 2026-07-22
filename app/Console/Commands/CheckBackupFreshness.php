<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A decrypt-and-restore drill can't run unattended on this server by design - the backup's private
 * key deliberately never touches it (see docs/BACKUPS.md), so there's nothing here that could
 * decrypt a backup to verify it even if it wanted to. What this checks instead, safely, without the
 * key: that a backup file actually landed on the off-VPS disk recently and isn't suspiciously
 * small/empty - catching "the nightly job silently stopped running" or "uploads are failing", the
 * two failure modes this command can detect without ever touching plaintext.
 */
#[Signature('backup:check-freshness')]
#[Description('Verify a recent, non-empty database backup exists on the backups disk; log critical if not')]
class CheckBackupFreshness extends Command
{
    private const MAX_AGE_HOURS = 26; // daily backup at 02:00 + slack, not the bare 24h

    public function handle(): int
    {
        if (! config('backup.gpg_recipient')) {
            $this->info('BACKUP_GPG_RECIPIENT is not set - backups are not configured, nothing to check.');

            return self::SUCCESS;
        }

        $disk = Storage::disk(config('backup.disk'));
        $cutoff = now()->subHours(self::MAX_AGE_HOURS);

        $recent = collect($disk->files('database'))
            ->map(function (string $path) use ($disk) {
                $timestamp = Str::before(pathinfo($path, PATHINFO_FILENAME), '.dump');
                $date = Carbon::createFromFormat('Y-m-d_His', $timestamp);

                return $date ? ['path' => $path, 'date' => $date, 'size' => $disk->size($path)] : null;
            })
            ->filter()
            ->sortByDesc('date')
            ->first();

        if (! $recent) {
            Log::critical('backup.freshness_check_failed', ['reason' => 'no backup files found on disk at all']);

            return self::FAILURE;
        }

        if ($recent['date']->lt($cutoff)) {
            Log::critical('backup.freshness_check_failed', [
                'reason' => 'latest backup is older than expected',
                'latest_backup_at' => $recent['date']->toIso8601String(),
                'max_age_hours' => self::MAX_AGE_HOURS,
            ]);

            return self::FAILURE;
        }

        if ($recent['size'] < 1024) {
            Log::critical('backup.freshness_check_failed', [
                'reason' => 'latest backup is suspiciously small - likely a failed/truncated dump or encrypt step',
                'path' => $recent['path'],
                'size_bytes' => $recent['size'],
            ]);

            return self::FAILURE;
        }

        $this->info("Latest backup {$recent['path']} is fresh ({$recent['date']->toIso8601String()}, {$recent['size']} bytes).");

        return self::SUCCESS;
    }
}
