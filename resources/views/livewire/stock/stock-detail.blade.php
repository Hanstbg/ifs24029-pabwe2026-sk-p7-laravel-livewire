<div class="mx-auto max-w-3xl space-y-6">
    <a href="/stocks" class="text-sm text-blue-600 hover:underline">&larr; Kembali</a>

    @if ($message)
        <div class="rounded bg-green-100 px-4 py-2 text-green-800">{{ $message }}</div>
    @endif

    <div class="rounded bg-white p-6 shadow space-y-4">
        <div class="flex items-start justify-between">
            <h1 class="text-2xl font-bold">{{ $stock->name }}</h1>
            <span class="rounded px-2 py-1 text-xs {{ $stock->status->badgeClass() }}">{{ $stock->status->label() }}</span>
        </div>

        @if ($stock->cover_image_path)
            <img src="{{ asset('storage/'.$stock->cover_image_path) }}" alt="Cover" class="max-h-64 rounded">
        @endif

        <div class="flex items-center gap-3">
            <button wire:click="adjust(-1)" class="rounded bg-gray-200 px-3 py-1">-</button>
            <span class="text-lg">{{ $stock->quantity }} {{ $stock->unit }}</span>
            <button wire:click="adjust(1)" class="rounded bg-gray-200 px-3 py-1">+</button>
        </div>

        <div class="prose max-w-none">
            {!! \Illuminate\Support\Str::markdown($stock->description ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
        </div>
    </div>

    <form wire:submit="save" class="rounded bg-white p-6 shadow space-y-3">
        <h2 class="font-semibold">Edit</h2>
        @foreach ([['name','Nama','text'],['sku','SKU','text'],['quantity','Quantity','number'],['unit','Satuan','text'],['price','Harga','number'],['min_stock','Min. stok','number']] as [$f,$l,$t])
            <div>
                <label class="block text-sm font-medium">{{ $l }}</label>
                <input type="{{ $t }}" wire:model="{{ $f }}" class="w-full rounded border border-gray-300 px-3 py-2">
                @error($f) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endforeach

        <div>
            <label class="block text-sm font-medium">Deskripsi (Markdown)</label>
            <div wire:ignore><div id="md-editor"></div></div>
        </div>

        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">Simpan</button>
    </form>

    <div class="rounded bg-white p-6 shadow space-y-2">
        <h2 class="font-semibold">Cover</h2>
        <input type="file" wire:model="cover" accept="image/*">
        @error('cover') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button wire:click="uploadCover" class="rounded bg-gray-800 px-3 py-1 text-sm text-white">Unggah</button>
    </div>

    <button wire:click="delete" wire:confirm="Hapus stok ini?" class="rounded bg-red-600 px-4 py-2 text-white">Hapus Stok</button>
</div>

@assets
<link rel="stylesheet" href="https://uicdn.toast.com/editor/3.2.2/toastui-editor.min.css">
<script src="https://uicdn.toast.com/editor/3.2.2/toastui-editor-all.min.js"></script>
@endassets

@script
<script>
    const editor = new toastui.Editor({
        el: document.querySelector('#md-editor'),
        height: '300px',
        initialEditType: 'markdown',
        previewStyle: 'tab',
        initialValue: $wire.description ?? '',
    });
    editor.on('change', () => { $wire.description = editor.getMarkdown(); });
</script>
@endscript
