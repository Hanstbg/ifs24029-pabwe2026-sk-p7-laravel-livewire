<div class="space-y-4">
    <h1 class="text-2xl font-bold">Daftar User</h1>
    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari user"
           class="w-64 rounded border border-gray-300 px-3 py-2">

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr><th class="px-4 py-2">Nama</th><th class="px-4 py-2">Username</th><th class="px-4 py-2">Role</th><th class="px-4 py-2">Jumlah Stok</th><th class="px-4 py-2">Aksi</th></tr>
            </thead>
            <tbody>
            @foreach ($users as $user)
                <tr class="border-t" wire:key="user-{{ $user->id }}">
                    <td class="px-4 py-2">{{ $user->name }}</td>
                    <td class="px-4 py-2">{{ $user->username }}</td>
                    <td class="px-4 py-2">{{ $user->role }}</td>
                    <td class="px-4 py-2">{{ $user->stocks_count }}</td>
                    <td class="px-4 py-2"><a href="/admin/users/{{ $user->id }}/stocks" class="text-blue-600 hover:underline">Lihat stok</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
