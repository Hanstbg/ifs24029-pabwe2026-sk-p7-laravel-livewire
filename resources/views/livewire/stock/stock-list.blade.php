<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Stok Barang</h1>
        <div class="flex gap-2">
            <button id="stock-export" wire:click="exportExcel" class="rounded bg-green-600 px-3 py-2 text-sm text-white">Export Excel</button>
            <button id="stock-create" wire:click="create" class="rounded bg-blue-600 px-3 py-2 text-sm text-white">Tambah Stok</button>
        </div>
    </div>

    @if ($message)
        <div class="rounded bg-green-100 px-4 py-2 text-green-800">{{ $message }}</div>
    @endif

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
                <tr>
                    <th class="px-4 py-2">Nama</th><th class="px-4 py-2">SKU</th>
                    <th class="px-4 py-2">Qty</th><th class="px-4 py-2">Harga</th>
                    <th class="px-4 py-2">Status</th><th class="px-4 py-2">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($stocks as $stock)
                <tr class="border-t" wire:key="stock-{{ $stock->id }}">
                    <td class="px-4 py-2"><a href="/stocks/{{ $stock->id }}" class="text-blue-600 hover:underline">{{ $stock->name }}</a></td>
                    <td class="px-4 py-2">{{ $stock->sku }}</td>
                    <td class="px-4 py-2">
                        <button wire:click="adjust({{ $stock->id }}, -1)" class="rounded bg-gray-200 px-2">-</button>
                        <span class="mx-2">{{ $stock->quantity }} {{ $stock->unit }}</span>
                        <button wire:click="adjust({{ $stock->id }}, 1)" class="rounded bg-gray-200 px-2">+</button>
                    </td>
                    <td class="px-4 py-2">{{ $stock->price !== null ? 'Rp '.number_format((float) $stock->price, 0, ',', '.') : '-' }}</td>
                    <td class="px-4 py-2"><span class="rounded px-2 py-1 text-xs {{ $stock->status->badgeClass() }}">{{ $stock->status->label() }}</span></td>
                    <td class="px-4 py-2 space-x-2">
                        <button wire:click="edit({{ $stock->id }})" class="text-blue-600">Edit</button>
                        <button wire:click="delete({{ $stock->id }})" wire:confirm="Hapus stok ini?" class="text-red-600">Hapus</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Belum ada stok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $stocks->links() }}

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded bg-white p-4 shadow">
            <h2 class="mb-2 font-semibold">Import Excel</h2>
            <p class="mb-2 text-xs text-gray-500">Kolom: name, sku, qty, unit, price, min_stock, status</p>
            <input type="file" wire:model="importFile" accept=".xlsx">
            @error('importFile') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <button wire:click="import" class="mt-2 rounded bg-gray-800 px-3 py-1 text-sm text-white">Import</button>
        </div>

        <div class="rounded bg-white p-4 shadow">
            <h2 class="mb-2 font-semibold">Generate dengan AI</h2>
            <input type="text" wire:model="aiPrompt" placeholder="mis. stok alat tulis kantor"
                   class="w-full rounded border border-gray-300 px-3 py-2">
            @error('aiPrompt') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <button wire:click="generateAi" class="mt-2 rounded bg-purple-600 px-3 py-1 text-sm text-white">Generate</button>
            @if ($aiError) <p class="mt-2 text-sm text-red-600">{{ $aiError }}</p> @endif

            @if (count($aiItems))
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($aiItems as $i => $item)
                        <li wire:key="ai-{{ $i }}">
                            <label><input type="checkbox" wire:model="aiSelected" value="{{ $i }}">
                                {{ $item['name'] }} ({{ $item['quantity'] }} {{ $item['unit'] }})</label>
                        </li>
                    @endforeach
                </ul>
                <button wire:click="saveAi" class="mt-2 rounded bg-blue-600 px-3 py-1 text-sm text-white">Simpan terpilih</button>
            @endif
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-black/50 p-4">
            <form wire:submit="save" class="w-full max-w-lg space-y-3 rounded bg-white p-6">
                <h2 class="text-lg font-bold">{{ $editingId ? 'Edit' : 'Tambah' }} Stok</h2>
                @foreach ([['name','Nama','text'],['sku','SKU','text'],['quantity','Quantity','number'],['unit','Satuan','text'],['price','Harga','number'],['min_stock','Min. stok','number']] as [$f,$l,$t])
                    <div>
                        <label class="block text-sm font-medium">{{ $l }}</label>
                        <input type="{{ $t }}" wire:model="{{ $f }}" class="w-full rounded border border-gray-300 px-3 py-2">
                        @error($f) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
                <div>
                    <label class="block text-sm font-medium">Deskripsi (Markdown)</label>
                    <textarea wire:model="description" rows="3" class="w-full rounded border border-gray-300 px-3 py-2"></textarea>
                </div>
                <p class="text-xs text-gray-500">Status otomatis: tersedia / menipis / habis berdasarkan quantity dan min. stok.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancel" class="rounded bg-gray-200 px-3 py-2">Batal</button>
                    <button type="submit" class="rounded bg-blue-600 px-3 py-2 text-white">Simpan</button>
                </div>
            </form>
        </div>
    @endif
</div>
