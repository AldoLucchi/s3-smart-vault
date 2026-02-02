<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}

                    <div class="mt-6 border-t pt-6">
                        <h3 class="text-lg font-medium mb-4">Upload to Vault</h3>

                        @if (session('status'))
                            <div class="mb-4 font-medium text-sm text-green-600">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="mb-4 text-red-600">
                                @foreach ($errors->all() as $error)
                                    <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        @endif

                        <form action="{{ route('vault.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <div>
                                <input type="file" name="vault_file" required class="border p-2 rounded w-full">
                            </div>
                            
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                {{ __('Upload to S3') }}
                            </button>
                        </form>
                    </div>

                    <div class="mt-10 border-t pt-6">
                        <h3 class="text-lg font-medium mb-4">Files in Vault</h3>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full border-collapse border border-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="border border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">File Name</th>
                                        <th class="border border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Size</th>
                                        <th class="border border-gray-200 px-4 py-2 text-center text-sm font-semibold text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($vaultFiles as $file)
                                        <tr class="hover:bg-gray-50">
                                            <td class="border border-gray-200 px-4 py-2 text-sm text-gray-800">{{ $file['name'] }}</td>
                                            <td class="border border-gray-200 px-4 py-2 text-sm text-gray-600">{{ round($file['size'] / 1024 / 1024, 2) }} MB</td>
                                                <td class="border border-gray-200 px-4 py-2 text-sm text-center">
                                                    <div class="flex items-center justify-center space-x-4">
                                                        @if($file['storage_class'] === 'GLACIER' || $file['storage_class'] === 'DEEP_ARCHIVE')
                                                            <form action="{{ route('vault.restore') }}" method="POST" class="inline">
                                                                @csrf
                                                                <input type="hidden" name="file_key" value="{{ $file['name'] }}">
                                                                <button type="submit" class="text-orange-600 hover:underline font-bold">
                                                                    🔥 Thaw
                                                                </button>
                                                            </form>
                                                        @else
                                                            <span class="text-green-600 font-medium italic">Ready ✅</span>
                                                            <a href="{{ route('vault.download', ['file_key' => $file['name']]) }}" 
                                                            class="text-green-600 hover:text-green-800 font-bold flex items-center">
                                                                📥 Download
                                                            </a>
                                                            <form action="{{ route('vault.freeze') }}" method="POST" class="inline">
                                                                @csrf
                                                                <input type="hidden" name="file_key" value="{{ $file['name'] }}">
                                                                <button type="submit" class="text-blue-500 hover:text-blue-700 font-bold">
                                                                    ❄️ Freeze
                                                                </button>
                                                            </form>
                                                        @endif

                                                        <form action="{{ route('vault.delete') }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <input type="hidden" name="file_key" value="{{ $file['name'] }}">
                                                            <button type="submit" class="text-red-500 hover:text-red-700 font-bold ml-4">
                                                                🗑️ Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="border border-gray-200 px-4 py-2 text-center text-sm text-gray-500">
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
        </div>
    </div>
</x-app-layout>