<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Vault</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-900 flex items-center justify-center min-h-screen">
    <div class="text-center">
        <h1 class="text-5xl font-bold text-white mb-8">❄️ Smart Vault</h1>
        <p class="text-gray-400 mb-8">Secure, cold storage for your most important files.</p>
        
        @if (Route::has('login'))
            <div class="space-x-4">
                @auth
                    <a href="{{ url('/dashboard') }}" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">Go to Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="bg-white text-gray-900 px-6 py-3 rounded-lg font-bold hover:bg-gray-200 transition">Access Vault</a>
                @endauth
            </div>
        @endif
    </div>
</body>
</html>