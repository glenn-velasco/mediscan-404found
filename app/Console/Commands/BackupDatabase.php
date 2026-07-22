<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Nightly encrypted database backup. Dumps Postgres via `pg_dump`, encrypts
 * the dump with GPG against a public key (the matching private key never
 * touches this server - see docs/BACKUPS.md), and uploads the ciphertext to
 * the `backups` disk. Skips cleanly (not an error) when unconfigured, so
 * it's safe to keep scheduled even on environments that haven't set up a
 * backup key yet (e.g. a fresh staging box).
 */
#[Signature('backup:database')]
#[Description('Dump the database, encrypt it with the configured GPG recipient, and upload it to the backups disk')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $recipient = config('backup.gpg_recipient');

        if (! $recipient) {
            $this->info('BACKUP_GPG_RECIPIENT is not set - skipping (see docs/BACKUPS.md).');

            return self::SUCCESS;
        }

        $timestamp = now()->format('Y-m-d_His');
        $dumpPath = storage_path("app/tmp/backup_{$timestamp}.dump");
        $encryptedPath = "{$dumpPath}.gpg";

        try {
            $this->dump($dumpPath);
            $this->encrypt($dumpPath, $encryptedPath, $recipient);
            $this->upload($encryptedPath, $timestamp);
            $this->pruneOld();
        } finally {
            @unlink($dumpPath);
            @unlink($encryptedPath);
        }

        $this->info("Backup {$timestamp} complete.");

        return self::SUCCESS;
    }

    private function dump(string $dumpPath): void
    {
        @mkdir(dirname($dumpPath), recursive: true);

        $connection = config('database.connections.'.config('database.default'));

        $process = new Process([
            'pg_dump',
            '-Fc',
            '-h', $connection['host'],
            '-p', (string) $connection['port'],
            '-U', $connection['username'],
            '-d', $connection['database'],
            '-f', $dumpPath,
        ], env: ['PGPASSWORD' => $connection['password'], 'PGSSLMODE' => $connection['sslmode'] ?? 'prefer']);

        $process->setTimeout(600);
        $process->mustRun();
    }

    private function encrypt(string $dumpPath, string $encryptedPath, string $recipient): void
    {
        $process = new Process([
            'gpg', '--batch', '--yes', '--trust-model', 'always',
            '--recipient', $recipient,
            '--output', $encryptedPath,
            '--encrypt', $dumpPath,
        ]);

        $process->setTimeout(300);
        $process->mustRun();
    }

    private function upload(string $encryptedPath, string $timestamp): void
    {
        $contents = file_get_contents($encryptedPath);

        if ($contents === false) {
            throw new \RuntimeException("Could not read encrypted backup at {$encryptedPath}");
        }

        Storage::disk(config('backup.disk'))
            ->put("database/{$timestamp}.dump.gpg", $contents);
    }

    /**
     * Flat retention: delete anything older than the configured window.
     * Simple on purpose - a tiered daily/weekly/monthly rotation can be
     * layered on later if the storage cost of flat retention ever matters;
     * for a solo-dev app it doesn't yet.
     */
    private function pruneOld(): void
    {
        $disk = Storage::disk(config('backup.disk'));
        $cutoff = now()->subDays(config('backup.retention_days'));

        foreach ($disk->files('database') as $path) {
            $name = pathinfo($path, PATHINFO_FILENAME); // "{timestamp}.dump"
            $timestamp = Str::before($name, '.dump');

            $date = Carbon::createFromFormat('Y-m-d_His', $timestamp);

            if ($date && $date->lt($cutoff)) {
                $disk->delete($path);
            }
        }
    }
}
