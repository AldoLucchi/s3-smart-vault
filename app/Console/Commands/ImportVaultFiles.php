<?php

namespace App\Console\Commands;

use App\Models\VaultFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportVaultFiles extends Command
{
    protected $signature   = 'vault:import {user_id}';
    protected $description = 'Import existing S3 files into the database for a given user';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $files  = Storage::disk('s3')->files('vault');

        $files = array_filter($files, fn($f) => substr_count($f, '/') === 1);

        $count = 0;

        foreach ($files as $s3Key) {
            $originalName = basename($s3Key);

            if (VaultFile::where('s3_key', $s3Key)->exists()) {
                $this->warn("Skipped (already imported): {$originalName}");
                continue;
            }

            try {
                $client = Storage::disk('s3')->getClient();
                $bucket = config('filesystems.disks.s3.bucket');
                $meta   = $client->headObject(['Bucket' => $bucket, 'Key' => $s3Key]);

                VaultFile::create([
                    'user_id'       => $userId,
                    'original_name' => $originalName,
                    's3_key'        => $s3Key,
                    'size'          => $meta['ContentLength'] ?? 0,
                    'storage_class' => $meta['StorageClass'] ?? 'STANDARD',
                    'mime_type'     => $meta['ContentType'] ?? null,
                ]);

                $this->info("Imported: {$originalName}");
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed: {$originalName} — " . $e->getMessage());
            }
        }

        $this->info("Done. {$count} files imported for user ID {$userId}.");
    }
}