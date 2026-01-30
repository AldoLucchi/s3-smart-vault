<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Exception;

class FileVaultController extends Controller
{
    public function index()
    {
        // We use cache to avoid multiple slow API calls to S3 on every refresh
        $vaultFiles = Cache::remember('s3_vault_list', 300, function () {
            $files = Storage::disk('s3')->files('vault');
            $s3Client = Storage::disk('s3')->getClient();
            $bucketName = config('filesystems.disks.s3.bucket');
            
            $fileList = [];
            foreach ($files as $file) {
                $meta = $s3Client->headObject([
                    'Bucket' => $bucketName,
                    'Key'    => $file,
                ]);

                $fileList[] = [
                    'name' => str_replace('vault/', '', $file),
                    'path' => $file,
                    'size' => $meta['ContentLength'] ?? 0,
                    'storage_class' => $meta['StorageClass'] ?? 'STANDARD',
                ];
            }
            return $fileList;
        });

        return view('dashboard', compact('vaultFiles'));
    }

    public function store(Request $request)
    {
        $request->validate(['vault_file' => 'required|file']);
        $file = $request->file('vault_file');
        $path = 'vault/' . $file->getClientOriginalName();

        try {
            Storage::disk('s3')->put($path, file_get_contents($file));
            
            Cache::forget('s3_vault_list');

            return back()->with('status', 'File uploaded successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Upload failed: ' . $e->getMessage()]);
        }
    }

    public function freeze(Request $request)
    {
        $fileKey = $request->input('file_key');
        $s3Client = Storage::disk('s3')->getClient();
        $bucketName = config('filesystems.disks.s3.bucket');

        try {
            $s3Client->copyObject([
                'Bucket'     => $bucketName,
                'Key'        => 'vault/' . $fileKey,
                'CopySource' => "{$bucketName}/vault/{$fileKey}",
                'StorageClass' => 'GLACIER',
                'MetadataDirective' => 'COPY',
            ]);

            Cache::forget('s3_vault_list');
            return back()->with('status', 'File frozen successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Freeze failed: ' . $e->getMessage()]);
        }
    }

    public function requestRestoration(Request $request)
    {
        $fileKey = $request->input('file_key');
        $s3Client = Storage::disk('s3')->getClient();
        $bucketName = config('filesystems.disks.s3.bucket');

        try {
            $s3Client->restoreObject([
                'Bucket' => $bucketName,
                'Key'    => 'vault/' . $fileKey,
                'RestoreRequest' => [
                    'Days' => 1,
                    'GlacierJobParameters' => ['Tier' => 'Standard'],
                ],
            ]);

            return back()->with('status', 'Restoration (thawing) started.');
        } catch (Exception $e) {
        if (str_contains($e->getMessage(), 'RestoreAlreadyInProgress')) {
            return back()->with('status', 'Patience! AWS is already thawing this file.');
        }
        return back()->withErrors(['error' => 'Restoration failed: ' . $e->getMessage()]);
        }
    }
        /**
     * Deletes a file from the S3 bucket and clears the local cache.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'file_key' => 'required|string',
        ]);

        $fileKey = $request->input('file_key');

        try {
            // Delete the file from the 'vault/' directory in S3
            Storage::disk('s3')->delete('vault/' . $fileKey);

            // Invalidate cache to refresh the file list
            Cache::forget('s3_vault_list');

            return back()->with('status', 'File deleted successfully from the vault.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Delete failed: ' . $e->getMessage()]);
        }
    }

    public function download(Request $request)
    {
        $request->validate([
            'file_key' => 'required|string',
        ]);

        $fileKey = 'vault/' . $request->input('file_key');

        try {
            $url = Storage::disk('s3')->temporaryUrl(
                $fileKey, 
                now()->addMinutes(15)
            );

            return redirect($url);
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Download failed: ' . $e->getMessage()]);
        }
    }
}