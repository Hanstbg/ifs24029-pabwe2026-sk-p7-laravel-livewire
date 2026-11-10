<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-bold">Profil</h1>

    @if ($message)
        <div class="rounded bg-green-100 px-4 py-2 text-green-800">{{ $message }}</div>
    @endif

    <form wire:submit="saveProfile" class="space-y-3 rounded bg-white p-6 shadow">
        <h2 class="font-semibold">Data Diri</h2>
        @foreach ([['name','Nama','text'],['username','Username','text'],['email','Email','email']] as [$f,$l,$t])
            <div>
                <label class="block text-sm font-medium">{{ $l }}</label>
                <input type="{{ $t }}" wire:model="{{ $f }}" class="w-full rounded border border-gray-300 px-3 py-2">
                @error($f) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endforeach
        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">Simpan</button>
    </form>

    <form wire:submit="savePassword" class="space-y-3 rounded bg-white p-6 shadow">
        <h2 class="font-semibold">Ubah Password</h2>
        @foreach ([['current_password','Password saat ini'],['new_password','Password baru'],['new_password_confirmation','Konfirmasi password baru']] as [$f,$l])
            <div>
                <label class="block text-sm font-medium">{{ $l }}</label>
                <input type="password" wire:model="{{ $f }}" class="w-full rounded border border-gray-300 px-3 py-2">
                @error($f) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endforeach
        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">Ubah Password</button>
    </form>

    <form wire:submit="savePhoto" class="space-y-3 rounded bg-white p-6 shadow">
        <h2 class="font-semibold">Foto Profil</h2>
        @if (auth()->user()->profile_photo_path)
            <img src="{{ asset('storage/'.auth()->user()->profile_photo_path) }}" alt="Foto" class="h-24 w-24 rounded-full object-cover">
        @endif
        <input type="file" wire:model="photo" accept="image/*">
        @error('photo') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">Unggah</button>
    </form>
</div>
