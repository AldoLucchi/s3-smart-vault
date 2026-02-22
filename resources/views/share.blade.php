<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared File — S3 Smart Vault</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 to-blue-50 flex items-center justify-center p-4">

    <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full overflow-hidden">

        <div class="bg-blue-600 px-6 py-5 text-white">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                </svg>
                <span class="font-bold text-lg">S3 Smart Vault</span>
            </div>
            <p class="mt-1 text-blue-200 text-sm">Someone shared a file with you</p>
        </div>

        <div class="px-6 py-6 space-y-4">
            <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl border border-gray-200">
                <span class="text-4xl">
                    @php
                        $ext = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
                        echo match($ext) {
                            'pdf'                               => '📄',
                            'doc', 'docx'                       => '📝',
                            'xls', 'xlsx', 'csv'                => '📊',
                            'jpg', 'jpeg', 'png', 'gif',
                            'webp', 'svg'                       => '🖼️',
                            'mp4', 'avi', 'mov', 'mkv'          => '🎥',
                            'mp3', 'wav', 'flac'                => '🎵',
                            'zip', 'rar', '7z', 'tar', 'gz'     => '📦',
                            default                             => '📁'
                        };
                    @endphp
                </span>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-800 truncate">{{ $file->original_name }}</p>
                    <p class="text-sm text-gray-500">{{ round($file->size / 1024 / 1024, 2) }} MB</p>
                </div>
            </div>

            <p class="text-xs text-gray-400 text-center">
                Link expires {{ $shareLink->expires_at->diffForHumans() }}
                ({{ $shareLink->expires_at->format('M d, Y H:i') }})
            </p>

            @if(str_starts_with($file->mime_type, 'image/'))
                <img src="{{ $url }}" alt="{{ $file->original_name }}"
                     class="w-full rounded-xl object-contain max-h-72 bg-gray-100">
            @elseif($file->mime_type === 'application/pdf')
                <iframe src="{{ $url }}" class="w-full h-64 rounded-xl border border-gray-200"></iframe>
            @elseif(str_starts_with($file->mime_type, 'video/'))
                <video src="{{ $url }}" controls class="w-full rounded-xl max-h-64"></video>
            @endif

            <a href="{{ $url }}" download
               class="flex items-center justify-center gap-2 w-full py-3 px-6 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-md">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download File
            </a>
        </div>

        <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 text-center">
            <p class="text-xs text-gray-400">Powered by <span class="font-semibold text-gray-500">S3 Smart Vault</span></p>
        </div>
    </div>

</body>
</html>