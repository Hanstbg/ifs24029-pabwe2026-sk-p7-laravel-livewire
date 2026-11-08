<div class="w-full max-w-md rounded-lg bg-white p-8 shadow">
    <h1 class="mb-6 text-2xl font-bold">Daftar</h1>

    <form wire:submit="submit" class="space-y-4">
        @foreach ([['name','Nama','text'],['username','Username','text'],['email','Email','email'],['password','Password','password'],['password_confirmation','Konfirmasi Password','password']] as [$f,$l,$t])
            <div>
                <label for="register-{{ $f }}" class="mb-1 block text-sm font-medium">{{ $l }}</label>
                <input id="register-{{ $f }}" type="{{ $t }}" wire:model="{{ $f }}"
                       class="w-full rounded border border-gray-300 px-3 py-2">
                @error($f) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endforeach

        <button id="register-submit" type="submit"
                class="w-full rounded bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">
            Daftar
        </button>
    </form>

    <p class="mt-4 text-center text-sm">
        Sudah punya akun? <a href="/login" class="text-blue-600 hover:underline">Masuk</a>
    </p>
</div>
