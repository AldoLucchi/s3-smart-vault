<?php

namespace App\Http\Controllers;

use App\Models\ShareLink;
use Illuminate\Support\Facades\Storage;

class ShareLinkController extends Controller
{
    public function show(string $token)
    {
        $shareLink = ShareLink::where('token', $token)
                              ->with('file')
                              ->firstOrFail();

        if ($shareLink->isExpired()) {
            abort(410, 'This link has expired.');
        }

        $url = Storage::disk('s3')->temporaryUrl(
            $shareLink->file->s3_key,
            now()->addMinutes(30)
        );

        return view('share', [
            'file'      => $shareLink->file,
            'url'       => $url,
            'shareLink' => $shareLink,
        ]);
    }
}