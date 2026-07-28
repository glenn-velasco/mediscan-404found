<?php

namespace App\Console\Commands;

use Aws\S3\Exception\S3Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;

#[Signature('storage:ensure-bucket')]
#[Description('Create the configured S3 bucket and set anonymous read policy so CDN can serve files')]
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
        } catch (S3Exception $e) {
            if ($e->getStatusCode() !== 404) {
                throw $e;
            }

            $client->createBucket(['Bucket' => $bucket]);
            $this->info("Bucket \"{$bucket}\" created.");
        }

        $policy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [[
                'Effect' => 'Allow',
                'Principal' => '*',
                'Action' => ['s3:GetObject'],
                'Resource' => ["arn:aws:s3:::{$bucket}/*"],
            ]],
        ]);

        $client->putBucketPolicy([
            'Bucket' => $bucket,
            'Policy' => $policy,
        ]);

        $this->info("Anonymous read policy set on \"{$bucket}\".");

        return self::SUCCESS;
    }
}
