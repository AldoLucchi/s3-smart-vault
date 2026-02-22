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

        $totalBytes = VaultFile::where('user_id', auth()->id())->sum('size');
        $totalMB    = round($totalBytes / 1024 / 1024, 2);
        $limitMB    = 10240;
        $percentage = min(($totalMB / $limitMB) * 100, 100);
        $isFull     = $totalMB >= $limitMB;
        $barColor   = $percentage >= 90 ? 'bg-red-600' : 'bg-blue-600';

        $vaultFiles = $query->paginate(20)->withQueryString();

        return view('dashboard', compact(
            'vaultFiles', 'totalMB', 'limitMB', 'percentage', 'isFull', 'barColor', 'search'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vault_file' => 'required|file|max:5102400'
        ]);

        $file   = $request->file('vault_file');
        $userId = auth()->id();
        $s3Key  = "vault/{$userId}/" . $file->getClientOriginalName();

        try {
            Storage::disk('s3')->put($s3Key, file_get_contents($file), [
                'StorageClass' => 'STANDARD',
                'ContentType'  => $file->getMimeType(),
            ]);

            VaultFile::create([
                'user_id'            => $userId,
                'original_name'      => $file->getClientOriginalName(),
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

            $s3Client->copyObject([
                'Bucket'            => $bucketName,
                'Key'               => $file->s3_key,
                'CopySource'        => "{$bucketName}/{$file->s3_key}",
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
                return back()->with('status', '✅ File already restored! You can download it now.');
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

    public function preview(Request $request)
    {
        $file = VaultFile::where('user_id', auth()->id())
                         ->findOrFail($request->input('file_id'));

        $url = Storage::disk('s3')->temporaryUrl($file->s3_key, now()->addMinutes(30));

        return response()->json(['url' => $url, 'mime' => $file->mime_type]);
    }

    public function createShareLink(Request $request)
    {
        $request->validate([
            'file_id' => 'required|integer',
            'hours'   => 'sometimes|integer|min:1|max:168',
        ]);

        $file = VaultFile::where('user_id', auth()->id())
                         ->findOrFail($request->input('file_id'));

        $shareLink = ShareLink::create([
            'vault_file_id' => $file->id,
            'user_id'       => auth()->id(),
            'token'         => Str::random(48),
            'expires_at'    => now()->addHours($request->input('hours', 24)),
        ]);

        return response()->json(['link' => route('share.show', $shareLink->token)]);
    }

    public function revokeShareLink(Request $request)
    {
        $shareLink = ShareLink::where('user_id', auth()->id())
                              ->findOrFail($request->input('link_id'));

        $shareLink->update(['revoked_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function shareLinks(Request $request)
    {
        $file = VaultFile::where('user_id', auth()->id())
                         ->findOrFail($request->input('file_id'));

        $links = ShareLink::where('vault_file_id', $file->id)
                          ->orderBy('created_at', 'desc')
                          ->get()
                          ->map(fn($link) => [
                              'id'         => $link->id,
                              'token'      => $link->token,
                              'expires_at' => $link->expires_at->format('M d, Y H:i'),
                              'expired'    => $link->isExpired(),
                              'revoked'    => $link->isRevoked(),
                              'valid'      => $link->isValid(),
                              'url'        => route('share.show', $link->token),
                          ]);

        return response()->json(['links' => $links]);
    }

    public function rename(Request $request)
    {
        $request->validate([
            'file_id'  => 'required|integer',
            'new_name' => 'required|string|max:255',
        ]);

        $file        = VaultFile::where('user_id', auth()->id())->findOrFail($request->input('file_id'));
        $newName     = $request->input('new_name');
        $originalExt = pathinfo($file->original_name, PATHINFO_EXTENSION);
        $newExt      = pathinfo($newName, PATHINFO_EXTENSION);

        // Preserve original extension if user did not include one
        if (!$newExt || strtolower($newExt) !== strtolower($originalExt)) {
            $newName = $newName . '.' . $originalExt;
        }

        $userId    = auth()->id();
        $newS3Key  = "vault/{$userId}/" . $newName;
        $s3Client  = Storage::disk('s3')->getClient();
        $bucket    = config('filesystems.disks.s3.bucket');

        try {
            // Copy to new key
            $s3Client->copyObject([
                'Bucket'            => $bucket,
                'Key'               => $newS3Key,
                'CopySource'        => "{$bucket}/{$file->s3_key}",
                'MetadataDirective' => 'COPY',
            ]);

            // Delete old key
            Storage::disk('s3')->delete($file->s3_key);

            // Update database
            $file->update([
                'original_name' => $newName,
                's3_key'        => $newS3Key,
            ]);

            return response()->json(['success' => true, 'new_name' => $newName]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Rename error: ' . $e->getMessage()], 500);
        }
    }

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