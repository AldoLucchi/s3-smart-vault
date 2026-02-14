<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            File Explorer
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-col md:flex-row w-full bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="w-full md:w-1/3 p-4 text-gray-900 sm:p-6 border-b md:border-b-0 md:border-r border-gray-200">
                    {{ __("You're logged in,") }} <b>{{ Auth::user()->name }}!</b>                
                </div>

                @php
                    $totalBytes = collect($vaultFiles)->sum('size');
                    $totalMB = round($totalBytes / 1024 / 1024, 2);
                    $limitMB = 10240; 
                    $percentage = min(($totalMB / $limitMB) * 100, 100);
                    $isFull = $totalMB >= $limitMB;
                    $barColor = $percentage >= 90 ? 'bg-red-600' : 'bg-blue-600';
                @endphp

                <div class="w-full md:w-1/3 p-4 text-gray-900 sm:p-6 border-b md:border-b-0 md:border-r border-gray-200">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-gray-700">
                            {{ __("Storage Used:") }} <strong>{{ $totalMB }} MB</strong> / <strong>{{ $limitMB }} MB</strong>
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-gray-200 rounded-full h-2.5">
                            <div class="{{ $barColor }} h-2.5 rounded-full transition-all duration-500" 
                                style="width: {{ $percentage }}%">
                            </div>
                        </div>
                        <span class="text-xs {{ $percentage >= 90 ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                            {{ round($percentage) }}%
                        </span>
                    </div>
                    @if($isFull)
                        <p class="mt-2 text-xs text-red-600 font-bold italic animate-pulse">
                            ⚠️ {{ __("Storage limit reached. Please contact the administrator.") }}
                        </p>
                    @endif
                </div>

                <div class="w-full md:w-1/3 p-4 sm:p-6">
                    @if (session('status'))
                        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
                            <p class="text-sm text-green-800">{{ session('status') }}</p>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm text-red-800">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form action="{{ route('vault.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                        @csrf
                        <div class="relative">
                            <input type="file" name="vault_file" required id="fileInput" class="hidden">
                            <label for="fileInput" class="flex items-center justify-center w-full px-6 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 cursor-pointer transition ease-in-out duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <span id="buttonText">{{ __('Select File & Upload to S3') }}</span>
                            </label>
                            <p class="mt-2 text-xs text-gray-500 text-center" id="fileNameDisplay"></p>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    File Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Size
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($vaultFiles as $file)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <div class="flex items-center">
                                            <span class="mr-3 text-2xl">
                                                @php
                                                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                                    $icon = match($extension) {
                                                        'pdf' => '📄',
                                                        'doc', 'docx' => '📝',
                                                        'xls', 'xlsx', 'csv' => '📊',
                                                        'ppt', 'pptx' => '📊',
                                                        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' => '🖼️',
                                                        'mp4', 'avi', 'mov', 'mkv' => '🎥',
                                                        'mp3', 'wav', 'flac', 'm4a' => '🎵',
                                                        'zip', 'rar', '7z', 'tar', 'gz' => '📦',
                                                        'txt', 'md' => '📃',
                                                        'json', 'xml' => '📋',
                                                        'html', 'css', 'js', 'php', 'py', 'java', 'cpp' => '💻',
                                                        default => '📁'
                                                    };
                                                    echo $icon;
                                                @endphp
                                            </span>
                                            {{ $file['name'] }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ round($file['size'] / 1024 / 1024, 2) }} MB
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($file['restoration_status'] === 'available')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                ✓ Available
                                            </span>
                                        @elseif($file['restoration_status'] === 'frozen')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                ❄️ Frozen
                                            </span>
                                        @elseif($file['restoration_status'] === 'restoring')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                ⏳ Restoring...
                                            </span>
                                        @elseif($file['restoration_status'] === 'restored')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                ✓ Restored (Temporary)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2">
                                            @if($file['restoration_status'] === 'frozen')
                                                <form action="{{ route('vault.restore') }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="file_key" value="{{ $file['name'] }}">
                                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-orange-500 rounded-lg hover:bg-orange-600 active:bg-orange-700 shadow-md hover:shadow-lg transition-all duration-150 font-semibold text-sm border border-orange-600">
                                                        🔥 Thaw
                                                    </button>
                                                </form>
                                            @elseif($file['restoration_status'] === 'restoring')
                                                <span class="inline-flex items-center px-4 py-2 bg-yellow-500 rounded-lg shadow-md font-semibold text-sm border border-yellow-600">
                                                    ⏳ Restoring (3-5h)
                                                </span>
                                            @elseif($file['restoration_status'] === 'restored' || $file['restoration_status'] === 'available')
                                                <a href="{{ route('vault.download', ['file_key' => $file['name']]) }}" 
                                                class="inline-flex items-center px-4 py-2 rounded-lg hover:bg-blue-600 active:bg-blue-700 shadow-md hover:shadow-lg transition-all duration-150 font-semibold text-sm border border-blue-600">
                                                    👁️ View/ Download
                                                </a>
                                                @if($file['storage_class'] === 'STANDARD' && $file['restoration_status'] === 'available')
                                                    <form action="{{ route('vault.freeze') }}" method="POST" class="inline">
                                                        @csrf
                                                        <input type="hidden" name="file_key" value="{{ $file['name'] }}">
                                                        <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg hover:bg-indigo-600 active:bg-indigo-700 shadow-md hover:shadow-lg transition-all duration-150 font-semibold text-sm border border-indigo-600">
                                                            ❄️ Freeze
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif

                                            @if($file['restoration_status'] !== 'restoring')
                                                <form action="{{ route('vault.delete') }}" method="POST" class="inline" onsubmit="return confirm('⚠️ Are you sure you want to delete this file?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="file_key" value="{{ $file['name'] }}">
                                                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg hover:bg-red-600 active:bg-red-700 shadow-md hover:shadow-lg transition-all duration-150 font-semibold text-sm border border-red-600">
                                                        🗑️ Delete
                                                    </button>
                                                </form>
                                            @else
                                                <span class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-500 rounded-lg shadow-sm font-semibold text-sm border border-gray-400 cursor-not-allowed opacity-60 ">
                                                    🗑️ Delete
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">
                                        No files found in the vault folder.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer - Full Width -->
    <footer class="w-full bg-white border-t border-gray-200 mt-8 absolute bottom-0">
        <div class="py-6">
            <div class="text-center">
                <div class="flex items-center justify-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path>
                    </svg>
                    <span class="text-sm font-semibold text-gray-700">
                        S3 Smart Vault
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        Version 1.0
                    </span>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    Secure cloud storage with intelligent archiving
                </p>
            </div>
        </div>
    </footer>

    <script>
        // Auto-submit form when file is selected
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                document.getElementById('fileNameDisplay').textContent = 'Selected: ' + file.name;
                document.getElementById('buttonText').textContent = 'Uploading...';
                document.getElementById('uploadForm').submit();
            }
        });
    </script>
</x-app-layout>