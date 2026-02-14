<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Exception;

class FileVaultController extends Controller
{
    /**
     * Display the vault dashboard with all files and their statuses.
     */
    public function index()
    {
        // Cache for 5 minutes to reduce S3 API calls
        $allFiles = Cache::remember('s3_vault_list', 300, function () {
            return $this->getVaultFileList();
        });

        // Paginate the results - 20 per page
        $perPage = 20;
        $currentPage = request()->get('page', 1);
        $pagedData = array_slice($allFiles, ($currentPage - 1) * $perPage, $perPage);
        
        $vaultFiles = new \Illuminate\Pagination\LengthAwarePaginator(
            $pagedData,
            count($allFiles),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('dashboard', compact('vaultFiles'));
    }

    /**
     * Upload a new file to the vault.
     */
    public function store(Request $request)
    {
        $request->validate([
            'vault_file' => 'required|file|max:1048576' // Max 1GB per request
        ]);

        $file = $request->file('vault_file');
        $path = 'vault/' . $file->getClientOriginalName();

        try {
            // Upload to S3 with standard storage class
            Storage::disk('s3')->put($path, file_get_contents($file), [
                'StorageClass' => 'STANDARD',
                'ContentType' => $file->getMimeType(),
            ]);
            
            // Clear cache to show new file
            Cache::forget('s3_vault_list');

            return back()->with('status', '✅ File uploaded successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Upload error: ' . $e->getMessage()]);
        }
    }

    /**
     * Freeze (archive) a file to Glacier storage.
     */
    public function freeze(Request $request)
    {
        $request->validate([
            'file_key' => 'required|string',
        ]);

        $fileKey = $request->input('file_key');
        $s3Client = Storage::disk('s3')->getClient();
        $bucketName = config('filesystems.disks.s3.bucket');

        try {
            // Check current storage class
            $headObject = $s3Client->headObject([
                'Bucket' => $bucketName,
                'Key'    => 'vault/' . $fileKey,
            ]);

            $currentClass = $headObject['StorageClass'] ?? 'STANDARD';

            // Don't freeze if already in Glacier
            if ($currentClass === 'GLACIER') {
                return back()->with('status', '❄️ This file is already frozen.');
            }

            // Copy object to same location with Glacier storage class
            $s3Client->copyObject([
                'Bucket'            => $bucketName,
                'Key'               => 'vault/' . $fileKey,
                'CopySource'        => "{$bucketName}/vault/{$fileKey}",
                'StorageClass'      => 'GLACIER',
                'MetadataDirective' => 'COPY',
            ]);

            Cache::forget('s3_vault_list');
            
            return back()->with('status', '❄️ File frozen successfully. Saving storage costs.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Freeze error: ' . $e->getMessage()]);
        }
    }

    /**
     * Request restoration (thaw) of a Glacier file.
     */
    public function requestRestoration(Request $request)
    {
        $request->validate([
            'file_key' => 'required|string',
        ]);

        $fileKey = $request->input('file_key');
        $s3Client = Storage::disk('s3')->getClient();
        $bucketName = config('filesystems.disks.s3.bucket');

        try {
            // Check current status
            $headObject = $s3Client->headObject([
                'Bucket' => $bucketName,
                'Key'    => 'vault/' . $fileKey,
            ]);

            $storageClass = $headObject['StorageClass'] ?? 'STANDARD';

            // If in Standard storage, no restoration needed
            if ($storageClass !== 'GLACIER') {
                return back()->with('status', '✅ This file is already available for download.');
            }

            // Check if restoration is already complete or in progress
            $restore = $headObject['Restore'] ?? null;
            
            if ($restore) {
                // Already restored and available
                if (str_contains($restore, 'ongoing-request="false"')) {
                    return back()->with('status', '✅ File already restored! You can download it now.');
                }
                
                // Restoration in progress
                if (str_contains($restore, 'ongoing-request="true"')) {
                    // Extract expiry date if available
                    preg_match('/expiry-date="([^"]+)"/', $restore, $matches);
                    $expiryInfo = isset($matches[1]) ? ' until ' . date('m/d/Y H:i', strtotime($matches[1])) : '';
                    
                    return back()->with('status', '⏳ Restoration in progress' . $expiryInfo . '. Refresh the page in a few minutes.');
                }
            }
            // Start restoration
            $s3Client->restoreObject([
                'Bucket' => $bucketName,
                'Key'    => 'vault/' . $fileKey,
                'RestoreRequest' => [
                    'Days' => 7, // File will be available for 7 days after restoration
                    'GlacierJobParameters' => [
                        'Tier' => 'Standard' // Options: Expedited (1-5min, $$), Standard (3-5hrs, $), Bulk (5-12hrs, ¢)
                    ],
                ],
            ]);

            Cache::forget('s3_vault_list');

            return back()->with('status', '🔥 Restoration started. The file will be available in 3-5 hours (Standard tier). Refresh the page later.');
            
        } catch (Exception $e) {
            // Handle already in progress error gracefully
            if (str_contains($e->getMessage(), 'RestoreAlreadyInProgress')) {
                return back()->with('status', '⏳ Restoration is already in progress. Please be patient, AWS is processing your file.');
            }
            
            return back()->withErrors(['error' => 'Restoration error: ' . $e->getMessage()]);
        }
    }

    /**
     * Download a file from the vault.
     */
    public function download(Request $request)
    {
        $request->validate([
            'file_key' => 'required|string',
        ]);

        $fileKey = 'vault/' . $request->input('file_key');
        $s3Client = Storage::disk('s3')->getClient();
        $bucketName = config('filesystems.disks.s3.bucket');

        try {
            // Check file status
            $headObject = $s3Client->headObject([
                'Bucket' => $bucketName,
                'Key'    => $fileKey,
            ]);

            $storageClass = $headObject['StorageClass'] ?? 'STANDARD';

            // If in Glacier, verify restoration status
            if ($storageClass === 'GLACIER') {
                $restore = $headObject['Restore'] ?? null;
                
                // Not restored yet
                if (!$restore) {
                    return back()->withErrors([
                        'error' => '❄️ This file is frozen in Glacier. You must use the "Thaw" button and wait for the process to complete.'
                    ]);
                }
                
                // Restoration in progress
                if (str_contains($restore, 'ongoing-request="true"')) {
                    return back()->withErrors([
                        'error' => '⏳ The file is being restored. Please wait a few minutes and try again.'
                    ]);
                }
                
                // Check if restoration has expired
                if (str_contains($restore, 'ongoing-request="false"')) {
                    // Restoration complete - proceed to download
                } else {
                    return back()->withErrors([
                        'error' => '❄️ The restoration has expired or is not available. Request a new restoration.'
                    ]);
                }
            }

            // Generate temporary URL (valid for 15 minutes)
            $url = Storage::disk('s3')->temporaryUrl(
                $fileKey, 
                now()->addMinutes(15)
            );

            return redirect($url);
            
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Download error: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete a file from the vault.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'file_key' => 'required|string',
        ]);

        $fileKey = $request->input('file_key');

        try {
            // Delete from S3
            Storage::disk('s3')->delete('vault/' . $fileKey);

            // Clear cache
            Cache::forget('s3_vault_list');

            return back()->with('status', '🗑️ File deleted successfully from vault.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Delete error: ' . $e->getMessage()]);
        }
    }

    /**
     * Get list of all files in the vault with their metadata.
     */
    private function getVaultFileList(): array
    {
        $files = Storage::disk('s3')->files('vault');
        $s3Client = Storage::disk('s3')->getClient();
        $bucketName = config('filesystems.disks.s3.bucket');
        
        $fileList = [];
        
        foreach ($files as $file) {
            try {
                $meta = $s3Client->headObject([
                    'Bucket' => $bucketName,
                    'Key'    => $file,
                ]);

                $storageClass = $meta['StorageClass'] ?? 'STANDARD';
                $restore = $meta['Restore'] ?? null;
                
                // Determine restoration status
                $restorationStatus = $this->determineRestorationStatus($storageClass, $restore);
                
                // Parse expiry date if available
                $expiryDate = null;
                if ($restore && preg_match('/expiry-date="([^"]+)"/', $restore, $matches)) {
                    $expiryDate = $matches[1];
                }

                $fileList[] = [
                    'name' => str_replace('vault/', '', $file),
                    'path' => $file,
                    'size' => $meta['ContentLength'] ?? 0,
                    'size_human' => $this->formatBytes($meta['ContentLength'] ?? 0),
                    'storage_class' => $storageClass,
                    'storage_class_label' => $this->getStorageClassLabel($storageClass),
                    'restoration_status' => $restorationStatus,
                    'restoration_expiry' => $expiryDate,
                    'last_modified' => $meta['LastModified'] ?? null,
                    'can_download' => $this->canDownload($storageClass, $restorationStatus),
                ];
            } catch (Exception $e) {
                // Skip files that can't be accessed
                continue;
            }
        }
        
        // Sort by last modified date (newest first)
        usort($fileList, function($a, $b) {
            return ($b['last_modified'] ?? '') <=> ($a['last_modified'] ?? '');
        });
        
        return $fileList;
    }

    /**
     * Determine the restoration status of a file.
     */
    private function determineRestorationStatus(string $storageClass, ?string $restore): string
    {
        // Standard storage doesn't need restoration
        if ($storageClass !== 'GLACIER') {
            return 'available';
        }

        // In Glacier storage
        if (!$restore) {
            return 'frozen';
        }

        // Check restoration status
        if (str_contains($restore, 'ongoing-request="true"')) {
            return 'restoring';
        }

        if (str_contains($restore, 'ongoing-request="false"')) {
            return 'restored';
        }

        return 'frozen';
    }

    /**
     * Check if a file can be downloaded.
     */
    private function canDownload(string $storageClass, string $restorationStatus): bool
    {
        // Standard storage is always downloadable
        if ($storageClass !== 'GLACIER') {
            return true;
        }

        // Glacier storage is only downloadable when restored
        return $restorationStatus === 'restored';
    }

    /**
     * Get human-readable storage class label.
     */
    private function getStorageClassLabel(string $storageClass): string
    {
        return match($storageClass) {
            'STANDARD' => 'Standard',
            'GLACIER' => 'Glacier (Archived)',
            default => $storageClass
        };
    }

    /**
     * Format bytes to human-readable size.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}