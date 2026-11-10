<div class="w-full max-w-md rounded-lg bg-white p-8 shadow">
    <h1 class="mb-6 text-2xl font-bold">Masuk</h1>

    <form wire:submit.prevent="submit" method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="login-username" class="mb-1 block text-sm font-medium">Username / Email</label>
            <input id="login-username" type="text" name="username" wire:model="login" autocomplete="username"
                   class="w-full rounded border border-gray-300 px-3 py-2">
            @error('login') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="login-password" class="mb-1 block text-sm font-medium">Password</label>
            <input id="login-password" type="password" name="password" wire:model="password" autocomplete="current-password"
                   class="w-full rounded border border-gray-300 px-3 py-2">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" wire:model="remember"> Ingat saya
        </label>

        <button id="login-submit" type="submit"
                class="w-full rounded bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">
            Masuk
        </button>
    </form>

    <p class="mt-4 text-center text-sm">
        Belum punya akun? <a href="/register" class="text-blue-600 hover:underline">Daftar</a>
    </p>
</div>
