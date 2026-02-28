<?php

namespace App\Http\Controllers;

use App\Models\VaultFile;
use App\Models\ShareLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class FileVaultController extends Controller
{
    /**
     * Display the vault dashboard with filtering and sorting.
     */
    public function index(Request $request)
    {
        $sort      = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');
        $search    = $request->get('search', '');

        $allowedSorts = ['original_name', 'size', 'created_at', 'storage_class'];
        if (!in_array($sort, $allowedSorts)) $sort = 'created_at';
        if (!in_array($direction, ['asc', 'desc'])) $direction = 'desc';

        $query = VaultFile::where('user_id', auth()->id());

        if ($search) {
            $query->where('original_name', 'like', "%{$search}%");
        }

        $query->orderBy($sort, $direction);

        // Calculate storage usage metrics for the UI progress bar
        $totalBytes = VaultFile::where('user_id', auth()->id())->sum('size');
        $totalMB    = round($totalBytes / 1024 / 1024, 2);
        $limitMB    = 10240; // 10GB Limit
        $percentage = min(($totalMB / $limitMB) * 100, 100);
        $isFull     = $totalMB >= $limitMB;
        $barColor   = $percentage >= 90 ? 'bg-red-600' : 'bg-blue-600';

        $vaultFiles = $query->paginate(20)->withQueryString();

        return view('dashboard', compact(
            'vaultFiles', 'totalMB', 'limitMB', 'percentage', 'isFull', 'barColor', 'search'
        ));
    }

    /**
     * Upload a new file to S3 and record it in the database.
     * Filenames are sanitized to prevent URL issues.
     */
    public function store(Request $request)
    {
        $request->validate([
            'vault_file' => 'required|file|max:5102400'
        ]);

        $file   = $request->file('vault_file');
        $userId = auth()->id();
        
        // Sanitize filename: convert to slug to avoid spaces and special character issues in S3
        $extension = $file->getClientOriginalExtension();
        $baseName  = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $safeName  = $baseName . '.' . $extension;

        $s3Key  = "vault/{$userId}/" . $safeName;

        try {
            Storage::disk('s3')->put($s3Key, file_get_contents($file), [
                'StorageClass' => 'STANDARD',
                'ContentType'  => $file->getMimeType(),
            ]);

            VaultFile::create([
                'user_id'            => $userId,
                'original_name'      => $safeName,
                's3_key'             => $s3Key,
                'size'               => $file->getSize(),
                'storage_class'      => 'STANDARD',
                'mime_type'          => $file->getMimeType(),
                'restoration_status' => 'available',
            ]);

            return back()->with('status', '✅ File uploaded successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Upload error: ' . $e->getMessage()]);
        }
    }

    /**
     * Change S3 Storage Class to GLACIER to save costs.
     */
    public function freeze(Request $request)
    {
        $file = VaultFile::where('user_id', auth()->id())
                         ->findOrFail($request->input('file_id'));

        $s3Client   = Storage::disk('s3')->getClient();
        $bucketName = config('filesystems.disks.s3.bucket');

        try {
            if ($file->storage_class === 'GLACIER') {
                return back()->with('status', '❄️ This file is already frozen.');
            }

            // CopySource must be manually encoded to handle spaces in existing legacy filenames
            $encodedSource = "{$bucketName}/" . str_replace('%2F', '/', rawurlencode($file->s3_key));

            $s3Client->copyObject([
                'Bucket'            => $bucketName,
                'Key'               => $file->s3_key,
                'CopySource'        => $encodedSource,
                'StorageClass'      => 'GLACIER',
                'MetadataDirective' => 'COPY',
            ]);

            $file->update([
                'storage_class'      => 'GLACIER',
                'restoration_status' => 'frozen',
            ]);

            return back()->with('status', '❄️ File frozen successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Freeze error: ' . $e->getMessage()]);
        }
    }

    /**
     * Request AWS to restore a file from Glacier to temporary standard access.
     */
    public function requestRestoration(Request $request)
    {
        $file = VaultFile::where('user_id', auth()->id())
                         ->findOrFail($request->input('file_id'));

        $s3Client   = Storage::disk('s3')->getClient();
        $bucketName = config('filesystems.disks.s3.bucket');

        try {
            if ($file->storage_class !== 'GLACIER') {
                return back()->with('status', '✅ File is already available.');
            }

            $headObject = $s3Client->headObject([
                'Bucket' => $bucketName,
                'Key'    => $file->s3_key,
            ]);

            $restore = $headObject['Restore'] ?? null;

            if ($restore && str_contains($restore, 'ongoing-request="true"')) {
                return back()->with('status', '⏳ Restoration already in progress.');
            }

            if ($restore && str_contains($restore, 'ongoing-request="false"')) {
                $file->update(['restoration_status' => 'restored']);
                return back()->with('status', '✅ File already restored!');
            }

            $s3Client->restoreObject([
                'Bucket'         => $bucketName,
                'Key'            => $file->s3_key,
                'RestoreRequest' => [
                    'Days'                 => 7,
                    'GlacierJobParameters' => ['Tier' => 'Standard'],
                ],
            ]);

            $file->update(['restoration_status' => 'restoring']);

            return back()->with('status', '🔥 Restoration started. Available in 3-5 hours.');
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'RestoreAlreadyInProgress')) {
                return back()->with('status', '⏳ Restoration already in progress.');
            }
            return back()->withErrors(['error' => 'Restoration error: ' . $e->getMessage()]);
        }
    }

    /**
     * Redirect user to a temporary signed S3 URL for downloading.
     */
    public function download(Request $request)
    {
        $file = VaultFile::where('user_id', auth()->id())
                         ->findOrFail($request->input('file_id'));

        try {
            $url = Storage::disk('s3')->temporaryUrl($file->s3_key, now()->addMinutes(15));
            return redirect($url);
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Download error: ' . $e->getMessage()]);
        }
    }

    /**
     * Permanently delete the file from both S3 and the local database.
     */
    public function destroy(Request $request)
    {
        $file = VaultFile::where('user_id', auth()->id())
                         ->findOrFail($request->input('file_id'));

        try {
            Storage::disk('s3')->delete($file->s3_key);
            $file->delete();
            return back()->with('status', '🗑️ File deleted successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Delete error: ' . $e->getMessage()]);
        }
    }

    /**
     * Get a signed URL for file previewing (images/PDFs).
     */
    public function preview(Request $request)
    {
        $file = VaultFile::where('user_id', auth()->id())
                         ->findOrFail($request->input('file_id'));

        $url = Storage::disk('s3')->temporaryUrl($file->s3_key, now()->addMinutes(30));

        return response()->json(['url' => $url, 'mime' => $file->mime_type]);
    }

    /**
     * Handle file renaming. Moves the object in S3 and updates the database record.
     */
    public function rename(Request $request)
    {
        $request->validate([
            'file_id'  => 'required|integer',
            'new_name' => 'required|string|max:255',
        ]);

        $file = VaultFile::where('user_id', auth()->id())->findOrFail($request->input('file_id'));
        
        // Sanitize the new name and preserve original extension
        $originalExt = pathinfo($file->original_name, PATHINFO_EXTENSION);
        $newNameClean = Str::slug(pathinfo($request->input('new_name'), PATHINFO_FILENAME));
        $finalName = $newNameClean . '.' . $originalExt;

        // Dynamic directory detection: keeps file in its current folder (root or user folder)
        $directory = dirname($file->s3_key); 
        $newS3Key  = ($directory === '.' ? '' : $directory . '/') . $finalName;
        
        $bucket    = config('filesystems.disks.s3.bucket');
        $s3Client  = Storage::disk('s3')->getClient();

        try {
            // Verify file exists in S3 before attempting rename
            if (!Storage::disk('s3')->exists($file->s3_key)) {
                return response()->json(['error' => 'Source file not found in S3 at: ' . $file->s3_key], 404);
            }

            // Encode source path to handle spaces in legacy filenames
            $encodedSource = "{$bucket}/" . str_replace('%2F', '/', rawurlencode($file->s3_key));

            // S3 requires Copy + Delete for renames
            $s3Client->copyObject([
                'Bucket'            => $bucket,
                'Key'               => $newS3Key,
                'CopySource'        => $encodedSource,
                'MetadataDirective' => 'COPY',
            ]);

            Storage::disk('s3')->delete($file->s3_key);

            $file->update([
                'original_name' => $finalName,
                's3_key'        => $newS3Key,
            ]);

            return response()->json(['success' => true, 'new_name' => $finalName]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Rename error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint to check if Glacier restoration has finished.
     */
    public function pollStatus(Request $request)
    {
        $files = VaultFile::where('user_id', auth()->id())
                          ->where('restoration_status', 'restoring')
                          ->get();

        $s3Client   = Storage::disk('s3')->getClient();
        $bucketName = config('filesystems.disks.s3.bucket');
        $updated    = [];

        foreach ($files as $file) {
            try {
                $headObject = $s3Client->headObject([
                    'Bucket' => $bucketName,
                    'Key'    => $file->s3_key,
                ]);

                $restore = $headObject['Restore'] ?? null;

                // If ongoing-request is false, the file is ready for download
                if ($restore && str_contains($restore, 'ongoing-request="false"')) {
                    $file->update(['restoration_status' => 'restored']);
                    $updated[] = $file->id;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return response()->json(['updated' => $updated]);
    }
}