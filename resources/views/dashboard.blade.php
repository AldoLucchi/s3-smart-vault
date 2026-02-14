<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-col md:flex-row w-full bg-white overflow-hidden shadow sm:rounded-lg">
                <!-- Sección 1: Welcome -->
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

                <!-- Sección 2: Storage -->
                <div class="w-full md:w-1/3 p-4 text-gray-900 sm:p-6 border-b md:border-b-0 md:border-r border-gray-200">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-gray-700">
                            {{ __("Storage Used:") }} <strong>{{ $totalMB }} MB</strong> / {{ $limitMB }} MB
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

                <!-- Sección 3: Upload -->
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

            <!-- Files List -->
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
                                        <button onclick="openPreview('{{ $file['name'] }}', '{{ route('vault.download', ['file_key' => $file['name']]) }}', '{{ $file['can_download'] }}')" class="flex items-center hover:text-blue-600 transition-colors">
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
                                        </button>
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
                                                ✓ Restored
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2">
                                            @if($file['restoration_status'] === 'frozen')
                                                <!-- File is frozen - show Thaw button -->
                                                <form action="{{ route('vault.restore') }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="file_key" value="{{ $file['name'] }}">
                                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-orange-500 rounded-lg hover:bg-orange-600 active:bg-orange-700 shadow-md hover:shadow-lg transition-all duration-150 font-semibold text-sm border border-orange-600">
                                                        🔥 Thaw
                                                    </button>
                                                </form>
                                            @elseif($file['restoration_status'] === 'restoring')
                                                <!-- File is being restored - show disabled state -->
                                                <span class="inline-flex items-center px-4 py-2 bg-yellow-500 rounded-lg shadow-md font-semibold text-sm border border-yellow-600">
                                                    ⏳ Restoring (3-5h)
                                                </span>
                                            @elseif($file['restoration_status'] === 'restored' || $file['restoration_status'] === 'available')
                                                <!-- File is available - show all actions -->
                                                <a href="{{ route('vault.download', ['file_key' => $file['name']]) }}" 
                                                class="inline-flex items-center px-4 py-2 bg-blue-500 rounded-lg hover:bg-blue-600 active:bg-blue-700 shadow-md hover:shadow-lg transition-all duration-150 font-semibold text-sm border border-blue-600">
                                                    👁️ View / Download
                                                </a>
                                                @if($file['storage_class'] === 'STANDARD')
                                                    <form action="{{ route('vault.freeze') }}" method="POST" class="inline">
                                                        @csrf
                                                        <input type="hidden" name="file_key" value="{{ $file['name'] }}">
                                                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-500 rounded-lg hover:bg-indigo-600 active:bg-indigo-700 shadow-md hover:shadow-lg transition-all duration-150 font-semibold text-sm border border-indigo-600">
                                                            ❄️ Freeze
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif

                                            <!-- Delete button - always available except during restoration -->
                                            @if($file['restoration_status'] !== 'restoring')
                                                <form action="{{ route('vault.delete') }}" method="POST" class="inline" onsubmit="return confirm('⚠️ Are you sure you want to delete this file?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="file_key" value="{{ $file['name'] }}">
                                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-500 rounded-lg hover:bg-red-600 active:bg-red-700 shadow-md hover:shadow-lg transition-all duration-150 font-semibold text-sm border border-red-600">
                                                        🗑️ Delete
                                                    </button>
                                                </form>
                                            @else
                                                <span class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-500 rounded-lg shadow-sm font-semibold text-sm border border-gray-400 cursor-not-allowed opacity-60">
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

    <!-- Preview Modal -->
    <div id="previewModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900" id="previewFileName"></h3>
                <button onclick="closePreview()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-8rem)]" id="previewContent">
                <!-- Preview content will be loaded here -->
            </div>
            <div class="flex items-center justify-end p-6 border-t border-gray-200 space-x-3">
                <button onclick="closePreview()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md font-medium transition-colors">
                    Close
                </button>
                <a id="downloadLink" href="#" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium transition-colors">
                    Download
                </a>
            </div>
        </div>
    </div>

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

        // Preview modal functions
        function openPreview(fileName, downloadUrl, canDownload) {
            const modal = document.getElementById('previewModal');
            const content = document.getElementById('previewContent');
            const fileNameDisplay = document.getElementById('previewFileName');
            const downloadLink = document.getElementById('downloadLink');
            
            fileNameDisplay.textContent = fileName;
            downloadLink.href = downloadUrl;
            
            // Check if file can be downloaded
            if (canDownload === '0' || canDownload === false) {
                content.innerHTML = `
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">❄️</div>
                        <p class="text-gray-700 font-medium text-lg">File is Frozen or Restoring</p>
                        <p class="text-gray-500 mt-2">This file cannot be previewed until restoration is complete.</p>
                    </div>
                `;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                return;
            }
            
            const extension = fileName.split('.').pop().toLowerCase();
            
            // Clear previous content
            content.innerHTML = '<div class="flex items-center justify-center py-12"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div></div>';
            
            // Show modal
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // Load preview based on file type
            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(extension)) {
                content.innerHTML = `<img src="${downloadUrl}" alt="${fileName}" class="max-w-full h-auto rounded-lg mx-auto">`;
            } else if (['mp4', 'webm', 'ogg'].includes(extension)) {
                content.innerHTML = `<video controls class="max-w-full h-auto rounded-lg mx-auto"><source src="${downloadUrl}" type="video/${extension}"></video>`;
            } else if (['mp3', 'wav', 'ogg'].includes(extension)) {
                content.innerHTML = `<audio controls class="w-full"><source src="${downloadUrl}" type="audio/${extension}"></audio>`;
            } else if (extension === 'pdf') {
                content.innerHTML = `<iframe src="${downloadUrl}" class="w-full h-[600px] rounded-lg border"></iframe>`;
            } else if (['txt', 'md', 'json', 'xml', 'css', 'js', 'html', 'php', 'py'].includes(extension)) {
                fetch(downloadUrl)
                    .then(response => response.text())
                    .then(text => {
                        content.innerHTML = `<pre class="bg-gray-50 p-4 rounded-lg overflow-x-auto text-sm"><code>${escapeHtml(text)}</code></pre>`;
                    })
                    .catch(() => {
                        content.innerHTML = '<div class="text-center text-gray-500 py-12">Unable to preview this file type</div>';
                    });
            } else {
                content.innerHTML = `
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">📁</div>
                        <p class="text-gray-500">Preview not available for this file type</p>
                        <p class="text-sm text-gray-400 mt-2">Click download to view the file</p>
                    </div>
                `;
            }
        }

        function closePreview() {
            const modal = document.getElementById('previewModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreview();
            }
        });

        // Close modal when clicking outside
        document.getElementById('previewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePreview();
            }
        });
    </script>
</x-app-layout>