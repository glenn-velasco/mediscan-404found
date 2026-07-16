<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;

#[Signature('storage:ensure-bucket')]
#[Description('Create the configured S3 bucket if it does not already exist, so deploys never silently upload into a missing bucket')]
class EnsureStorageBucketExists extends Command
{
    public function handle(): int
    {
        $bucket = config('filesystems.disks.s3.bucket');

        /** @var AwsS3V3Adapter $disk */
        $disk = Storage::disk('s3');
        $client = $disk->getClient();

        try {
            $client->headBucket(['Bucket' => $bucket]);
            $this->info("Bucket \"{$bucket}\" already exists.");

            return self::SUCCESS;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($e->getStatusCode() !== 404) {
                throw $e;
            }
        }

        $client->createBucket(['Bucket' => $bucket]);
        $this->info("Bucket \"{$bucket}\" created.");

        return self::SUCCESS;
    }
}
