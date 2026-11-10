<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }} - {{ config('app.name') }}</title>
    <meta name="description" content="Aplikasi pengelolaan stok barang berbasis Laravel Livewire.">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-100 text-gray-900">
@auth
    <div class="flex min-h-screen">
        <aside class="w-60 bg-gray-900 text-gray-100 flex flex-col p-4">
            <div class="text-xl font-bold mb-6">{{ config('app.name') }}</div>
            <nav class="flex-1 space-y-1">
                @if (auth()->user()->isAdmin())
                    <a href="/admin/users" class="block rounded px-3 py-2 hover:bg-gray-700">Daftar User</a>
                @else
                    <a href="/stocks" class="block rounded px-3 py-2 hover:bg-gray-700">Stok Barang</a>
                @endif
                <a href="/profile" class="block rounded px-3 py-2 hover:bg-gray-700">Profil</a>
            </nav>
            <div class="text-sm text-gray-400 mb-2">
                {{ auth()->user()->name }} ({{ auth()->user()->role }})
            </div>
            <form method="POST" action="/logout">
                @csrf
                <button type="submit" class="w-full rounded bg-red-600 px-3 py-2 text-sm hover:bg-red-700">Keluar</button>
            </form>
        </aside>
        <main class="flex-1 p-6">{{ $slot }}</main>
    </div>
@else
    <main class="min-h-screen flex items-center justify-center p-4">{{ $slot }}</main>
@endauth
    @livewireScripts
</body>
</html>
