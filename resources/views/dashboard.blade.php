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
                <div class="w-full md:w-1/3 p-4 text-gray-900 sm:p-6 border-b md:border-b-0 md:border-r border-gray-200">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-gray-700">
                            {{ __("Storage Used:") }} <strong>{{ $totalMB }} MB</strong> / <strong>{{ $limitMB }} MB</strong>
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-gray-200 rounded-full h-2.5">
                            <div class="{{ $barColor }} h-2.5 rounded-full transition-all duration-500"
                                style="width: {{ $percentage }}%"></div>
                        </div>
                        <span class="text-xs {{ $percentage >= 90 ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                            {{ round($percentage) }}%
                        </span>
                    </div>
                    @if($isFull)
                        <p class="mt-2 text-xs text-red-600 font-bold italic animate-pulse">
                            ⚠️ {{ __("Storage limit reached.") }}
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
                        <input type="file" name="vault_file" required id="fileInput" class="hidden">
                        <div id="dropZone" class="flex flex-col items-center justify-center w-full px-4 py-5 border-2 border-dashed border-blue-300 rounded-lg cursor-pointer bg-blue-50 hover:bg-blue-100 transition-all duration-200 text-center">
                            <svg class="w-7 h-7 text-blue-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <span id="buttonText" class="text-sm font-semibold text-blue-600">Drag & drop or click to upload</span>
                            <p class="text-xs text-gray-400 mt-1" id="fileNameDisplay">jpg, png, pdf, doc, zip...</p>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-6 py-3 border-b border-gray-200">
                    <input type="text" id="searchInput" value="{{ $search }}" placeholder="🔍 Search files..."
                        class="w-full sm:w-72 px-4 py-2 text-sm border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent transition">
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-2/5">
                                    <a href="{{ route('dashboard', array_merge(request()->query(), ['sort' => 'original_name', 'direction' => request('sort') === 'original_name' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 hover:text-gray-700">
                                        File Name
                                        @if(request('sort') === 'original_name') {!! request('direction') === 'asc' ? '↑' : '↓' !!} @endif
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">
                                    <a href="{{ route('dashboard', array_merge(request()->query(), ['sort' => 'size', 'direction' => request('sort') === 'size' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 hover:text-gray-700">
                                        Size
                                        @if(request('sort') === 'size') {!! request('direction') === 'asc' ? '↑' : '↓' !!} @endif
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($vaultFiles as $file)
                                @php
                                    $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
                                    $icon = match($extension) {
                                        'pdf'                               => '📄',
                                        'doc', 'docx'                       => '📝',
                                        'xls', 'xlsx', 'csv'                => '📊',
                                        'ppt', 'pptx'                       => '📊',
                                        'jpg', 'jpeg', 'png', 'gif',
                                        'webp', 'svg'                       => '🖼️',
                                        'mp4', 'avi', 'mov', 'mkv'          => '🎥',
                                        'mp3', 'wav', 'flac', 'm4a'         => '🎵',
                                        'zip', 'rar', '7z', 'tar', 'gz'     => '📦',
                                        'txt', 'md'                         => '📃',
                                        'json', 'xml'                       => '📋',
                                        'html', 'css', 'js', 'php',
                                        'py', 'java', 'cpp'                 => '💻',
                                        default                             => '📁'
                                    };
                                    $isPreviewable = in_array($file->mime_type, [
                                        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                                        'application/pdf',
                                        'video/mp4', 'video/webm',
                                    ]);
                                    $status = $file->restoration_status ?? ($file->storage_class === 'STANDARD' ? 'available' : 'frozen');
                                @endphp
                                <tr class="hover:bg-gray-50" id="file-row-{{ $file->id }}">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        <div class="flex items-center gap-2">
                                            <span class="text-2xl">{{ $icon }}</span>
                                            <span class="break-words file-name-{{ $file->id }}">{{ $file->original_name }}</span>
                                            <button onclick="openRename({{ $file->id }}, '{{ addslashes($file->original_name) }}')"
                                                class="text-gray-300 hover:text-gray-500 transition ml-1" title="Rename">
                                                ✏️
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ round($file->size / 1024 / 1024, 2) }} MB
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm" id="status-cell-{{ $file->id }}">
                                        @if($status === 'available')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">✓ Available</span>
                                        @elseif($status === 'frozen')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">❄️ Frozen</span>
                                        @elseif($status === 'restoring')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 animate-pulse" data-polling="true">⏳ Restoring...</span>
                                        @elseif($status === 'restored')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">✓ Restored</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2 flex-wrap">

                                            @if($status === 'available' || $status === 'restored')
                                                @if($isPreviewable)
                                                    <button onclick="openPreview({{ $file->id }}, '{{ $file->mime_type }}')"
                                                        class="btn-expand inline-flex items-center justify-center w-9 h-9 rounded-full border border-purple-400 bg-purple-100/60 text-purple-700 shadow-sm backdrop-blur-sm hover:bg-purple-200/80 transition-all duration-300 overflow-hidden"
                                                        data-label="Preview">👁️</button>
                                                @endif

                                                <form action="{{ route('vault.download') }}" method="GET" class="inline">
                                                    <input type="hidden" name="file_id" value="{{ $file->id }}">
                                                    <button type="submit"
                                                        class="btn-expand inline-flex items-center justify-center w-9 h-9 rounded-full border border-green-400 bg-green-100/60 text-green-700 shadow-sm backdrop-blur-sm hover:bg-green-200/80 transition-all duration-300 overflow-hidden"
                                                        data-label="Download">⬇️</button>
                                                </form>

                                                <form action="{{ route('vault.freeze') }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="file_id" value="{{ $file->id }}">
                                                    <button type="submit"
                                                        class="btn-expand inline-flex items-center justify-center w-9 h-9 rounded-full border border-blue-400 bg-blue-100/60 text-blue-700 shadow-sm backdrop-blur-sm hover:bg-blue-200/80 transition-all duration-300 overflow-hidden"
                                                        data-label="Freeze">❄️</button>
                                                </form>

                                                <button onclick="openShare({{ $file->id }})"
                                                    class="btn-expand inline-flex items-center justify-center w-9 h-9 rounded-full border border-yellow-400 bg-yellow-100/60 text-yellow-700 shadow-sm backdrop-blur-sm hover:bg-yellow-200/80 transition-all duration-300 overflow-hidden"
                                                    data-label="Share">🔗</button>
                                            @endif

                                            @if($status === 'frozen')
                                                <form action="{{ route('vault.restore') }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="file_id" value="{{ $file->id }}">
                                                    <button type="submit"
                                                        class="btn-expand inline-flex items-center justify-center w-9 h-9 rounded-full border border-orange-400 bg-orange-100/60 text-orange-700 shadow-sm backdrop-blur-sm hover:bg-orange-200/80 transition-all duration-300 overflow-hidden"
                                                        data-label="Thaw">🔥</button>
                                                </form>
                                            @endif

                                            <form action="{{ route('vault.delete') }}" method="POST" class="inline" onsubmit="return confirm('⚠️ Are you sure you want to delete this file?');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="file_id" value="{{ $file->id }}">
                                                <button type="submit"
                                                    class="btn-expand inline-flex items-center justify-center w-9 h-9 rounded-full border border-red-400 bg-red-100/60 text-red-700 shadow-sm backdrop-blur-sm hover:bg-red-200/80 transition-all duration-300 overflow-hidden"
                                                    data-label="Delete">🗑️</button>
                                            </form>

                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">No files found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($vaultFiles->hasPages())
                <div class="mt-4 flex justify-center">
                    {{ $vaultFiles->links() }}
                </div>
            @endif

        </div>
    </div>

    {{-- Preview Modal --}}
    <div id="previewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full mx-4 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h3 class="font-semibold text-gray-800">Preview</h3>
                <button onclick="closePreview()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <div id="previewContent" class="flex items-center justify-center bg-gray-50 min-h-64 max-h-[75vh] overflow-auto p-4">
                <div class="text-gray-400 text-sm">Loading...</div>
            </div>
        </div>
    </div>

    {{-- Share Modal --}}
    <div id="shareModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h3 class="font-semibold text-gray-800">🔗 Share File</h3>
                <button onclick="closeShare()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700">Link expires in:</label>
                    <select id="shareHours" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                        <option value="1">1 hour</option>
                        <option value="24" selected>24 hours</option>
                        <option value="72">3 days</option>
                        <option value="168">7 days</option>
                    </select>
                </div>
                <button onclick="generateShareLink()" class="w-full py-2 px-4 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                    Generate Link
                </button>
                <div id="shareLinkResult" class="hidden">
                    <label class="text-sm font-medium text-gray-700">Your link:</label>
                    <div class="flex gap-2 mt-1">
                        <input id="shareLinkInput" type="text" readonly class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50 focus:outline-none">
                        <button onclick="copyShareLink()" class="px-3 py-2 bg-gray-100 rounded-lg text-sm hover:bg-gray-200 transition">Copy</button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Anyone with this link can download the file.</p>
                </div>

                {{-- Active links list --}}
                <div id="activeLinksSection" class="hidden">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-gray-700">Active links:</label>
                    </div>
                    <div id="activeLinksList" class="space-y-2 max-h-40 overflow-y-auto"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Rename Modal --}}
    <div id="renameModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h3 class="font-semibold text-gray-800">✏️ Rename File</h3>
                <button onclick="closeRename()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <input type="text" id="renameInput"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                    placeholder="New file name">
                <button onclick="submitRename()"
                    class="w-full py-2 px-4 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                    Rename
                </button>
            </div>
        </div>
    </div>

    <footer class="w-full bg-gray-50 border-t border-gray-200 mt-12 -mb-6">
        <div class="py-6 text-center">
            <div class="flex items-center justify-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                </svg>
                <span class="text-sm font-semibold text-gray-700">S3 Smart Vault</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Version 2.1.0</span>
            </div>
            <p class="mt-2 text-xs text-gray-500">Secure cloud storage with intelligent archiving</p>
        </div>
    </footer>

    <script>
        // ── Drag & Drop ──────────────────────────────────────────────────────────
        const dropZone  = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');

        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-blue-500', 'bg-blue-100');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('border-blue-500', 'bg-blue-100');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-blue-500', 'bg-blue-100');
            const file = e.dataTransfer.files[0];
            if (file) {
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                submitUpload(file);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files[0]) submitUpload(e.target.files[0]);
        });

        function submitUpload(file) {
            document.getElementById('fileNameDisplay').textContent = 'Uploading: ' + file.name;
            document.getElementById('buttonText').textContent      = 'Uploading...';
            dropZone.classList.add('opacity-60', 'pointer-events-none');
            document.getElementById('uploadForm').submit();
        }

        // ── Search ───────────────────────────────────────────────────────────────
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const params = new URLSearchParams(window.location.search);
                params.set('search', this.value);
                params.delete('page');
                window.location.search = params.toString();
            }, 400);
        });

        // ── Expand Buttons ───────────────────────────────────────────────────────
        document.querySelectorAll('.btn-expand').forEach(btn => {
            const label = btn.dataset.label;
            const icon  = btn.innerHTML.trim();

            btn.addEventListener('mouseenter', () => {
                btn.style.width        = 'auto';
                btn.style.paddingLeft  = '0.75rem';
                btn.style.paddingRight = '0.75rem';
                btn.innerHTML = `${icon} <span style="margin-left:6px;white-space:nowrap;font-size:0.8rem;font-weight:600">${label}</span>`;
            });

            btn.addEventListener('mouseleave', () => {
                btn.style.width        = '2.25rem';
                btn.style.paddingLeft  = '';
                btn.style.paddingRight = '';
                btn.innerHTML = icon;
            });
        });

        // ── Preview Modal ────────────────────────────────────────────────────────
        function openPreview(fileId, mime) {
            document.getElementById('previewModal').classList.remove('hidden');
            document.getElementById('previewContent').innerHTML = '<div class="text-gray-400 text-sm">Loading...</div>';

            fetch(`/vault/preview?file_id=${fileId}`)
                .then(r => r.json())
                .then(({ url, mime }) => {
                    let html = '';
                    if (mime.startsWith('image/')) {
                        html = `<img src="${url}" class="max-w-full max-h-[70vh] object-contain rounded">`;
                    } else if (mime === 'application/pdf') {
                        html = `<iframe src="${url}" class="w-full h-[70vh] rounded border-0"></iframe>`;
                    } else if (mime.startsWith('video/')) {
                        html = `<video src="${url}" controls class="max-w-full max-h-[70vh] rounded"></video>`;
                    } else {
                        html = `<p class="text-gray-500 text-sm">Preview not available.</p>`;
                    }
                    document.getElementById('previewContent').innerHTML = html;
                });
        }

        function closePreview() {
            document.getElementById('previewModal').classList.add('hidden');
            document.getElementById('previewContent').innerHTML = '';
        }

        // ── Share Modal ──────────────────────────────────────────────────────────
        let currentShareFileId = null;

        function openShare(fileId) {
            currentShareFileId = fileId;
            document.getElementById('shareLinkResult').classList.add('hidden');
            document.getElementById('shareModal').classList.remove('hidden');
            loadActiveLinks(fileId);
        }

        function closeShare() {
            document.getElementById('shareModal').classList.add('hidden');
            currentShareFileId = null;
        }

        function generateShareLink() {
            const hours = document.getElementById('shareHours').value;

            fetch('/vault/share', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ file_id: currentShareFileId, hours: parseInt(hours) })
            })
            .then(r => r.json())
            .then(({ link }) => {
                document.getElementById('shareLinkInput').value = link;
                document.getElementById('shareLinkResult').classList.remove('hidden');
                loadActiveLinks(currentShareFileId);
            });
        }

        function loadActiveLinks(fileId) {
            fetch(`/vault/share/links?file_id=${fileId}`)
                .then(r => r.json())
                .then(({ links }) => {
                    const section = document.getElementById('activeLinksSection');
                    const list    = document.getElementById('activeLinksList');

                    const validLinks = links.filter(l => l.valid);

                    if (validLinks.length === 0) {
                        section.classList.add('hidden');
                        return;
                    }

                    section.classList.remove('hidden');
                    list.innerHTML = validLinks.map(link => `
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg text-xs gap-2">
                            <span class="text-gray-500 truncate flex-1">Expires ${link.expires_at}</span>
                            <button onclick="revokeLink(${link.id})"
                                class="text-red-500 hover:text-red-700 font-semibold whitespace-nowrap transition">
                                Revoke
                            </button>
                        </div>
                    `).join('');
                });
        }

        function revokeLink(linkId) {
            if (!confirm('Revoke this link? Anyone with it will lose access.')) return;

            fetch('/vault/share/revoke', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ link_id: linkId })
            })
            .then(r => r.json())
            .then(() => loadActiveLinks(currentShareFileId));
        }

        function copyShareLink() {
            const input = document.getElementById('shareLinkInput');
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value);
            } else {
                input.select();
                document.execCommand('copy');
            }
            event.target.textContent = '✓ Copied!';
            setTimeout(() => event.target.textContent = 'Copy', 2000);
        }

        // ── Rename Modal ─────────────────────────────────────────────────────────
        let currentRenameFileId = null;

        function openRename(fileId, currentName) {
            currentRenameFileId = fileId;
            document.getElementById('renameInput').value = currentName;
            document.getElementById('renameModal').classList.remove('hidden');
            document.getElementById('renameInput').focus();
        }

        function closeRename() {
            document.getElementById('renameModal').classList.add('hidden');
            currentRenameFileId = null;
        }

        function submitRename() {
            const newName = document.getElementById('renameInput').value.trim();
            if (!newName) return;

            fetch('/vault/rename', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ file_id: currentRenameFileId, new_name: newName })
            })
            .then(r => r.json())
            .then(({ success, new_name, error }) => {
                if (success) {
                    document.querySelector(`.file-name-${currentRenameFileId}`).textContent = new_name;
                    closeRename();
                } else {
                    alert(error || 'Rename failed.');
                }
            });
        }

        // ── Glacier Polling ──────────────────────────────────────────────────────
        const hasRestoringFiles = document.querySelectorAll('[data-polling="true"]').length > 0;

        if (hasRestoringFiles) {
            const pollInterval = setInterval(() => {
                fetch('/vault/poll-status')
                    .then(r => r.json())
                    .then(({ updated }) => {
                        if (updated.length > 0) {
                            // Reload the page to reflect new status
                            clearInterval(pollInterval);
                            window.location.reload();
                        }
                    });
            }, 30000); // Check every 30 seconds
        }

        // ── Close modals on Escape ───────────────────────────────────────────────
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closePreview();
                closeShare();
                closeRename();
            }
        });
    </script>
</x-app-layout>