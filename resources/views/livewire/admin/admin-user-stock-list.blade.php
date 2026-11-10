<div class="space-y-4">
    <a href="/admin/users" class="text-sm text-blue-600 hover:underline">&larr; Kembali</a>
    <h1 class="text-2xl font-bold">Stok milik {{ $user->name }} <span class="text-sm font-normal text-gray-500">(hanya baca)</span></h1>

    <div class="flex gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama / SKU"
               class="w-64 rounded border border-gray-300 px-3 py-2">
        <select wire:model.live="statusFilter" class="rounded border border-gray-300 px-3 py-2">
            <option value="">Semua status</option>
            @foreach (\App\Features\Stock\Enums\StockStatus::cases() as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr><th class="px-4 py-2">Nama</th><th class="px-4 py-2">SKU</th><th class="px-4 py-2">Qty</th><th class="px-4 py-2">Harga</th><th class="px-4 py-2">Status</th></tr>
            </thead>
            <tbody>
            @forelse ($stocks as $stock)
                <tr class="border-t" wire:key="stock-{{ $stock->id }}">
                    <td class="px-4 py-2">{{ $stock->name }}</td>
                    <td class="px-4 py-2">{{ $stock->sku }}</td>
                    <td class="px-4 py-2">{{ $stock->quantity }} {{ $stock->unit }}</td>
                    <td class="px-4 py-2">{{ $stock->price !== null ? 'Rp '.number_format((float) $stock->price, 0, ',', '.') : '-' }}</td>
                    <td class="px-4 py-2"><span class="rounded px-2 py-1 text-xs {{ $stock->status->badgeClass() }}">{{ $stock->status->label() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada stok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $stocks->links() }}
</div>
