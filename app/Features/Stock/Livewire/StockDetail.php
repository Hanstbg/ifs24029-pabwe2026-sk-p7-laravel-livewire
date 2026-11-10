<?php

namespace App\Features\Stock\Livewire;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Detail Stok')]
class StockDetail extends Component
{
    use WithFileUploads;

    public Stock $stock;

    public $name = '';

    public $sku = '';

    public $description = '';

    public $quantity = 0;

    public $unit = 'pcs';

    public $price = '';

    public $min_stock = 5;

    public $cover = null;

    public ?string $message = null;

    public function mount(Stock $stock): void
    {
        Gate::authorize('view', $stock);

        $this->stock = $stock;
        $this->fillFromStock();
    }

    public function save(StockService $service): void
    {
        Gate::authorize('update', $this->stock);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
        ]);

        $data['price'] = $data['price'] === '' ? null : $data['price'];
        $data['sku'] = $data['sku'] === '' ? null : $data['sku'];
        $data['unit'] = (string) ($data['unit'] ?? '');

        $this->stock = $service->update($this->stock, $data);
        $this->fillFromStock();
        $this->message = 'Stok berhasil disimpan.';
    }

    public function adjust(int $delta, StockService $service): void
    {
        Gate::authorize('update', $this->stock);

        $this->stock = $service->adjustQuantity($this->stock, $delta);
        $this->fillFromStock();
    }

    public function uploadCover(StockService $service): void
    {
        Gate::authorize('update', $this->stock);
        $this->validate(['cover' => ['required', 'image', 'max:2048']]);

        $this->stock = $service->setCover($this->stock, $this->cover);
        $this->cover = null;
        $this->message = 'Cover berhasil diunggah.';
    }

    public function delete(StockService $service)
    {
        Gate::authorize('delete', $this->stock);
        $service->delete($this->stock);

        return $this->redirect('/stocks');
    }

    private function fillFromStock(): void
    {
        $this->name = $this->stock->name;
        $this->sku = $this->stock->sku ?? '';
        $this->description = $this->stock->description ?? '';
        $this->quantity = $this->stock->quantity;
        $this->unit = $this->stock->unit;
        $this->price = $this->stock->price ?? '';
        $this->min_stock = $this->stock->min_stock;
    }

    public function render()
    {
        return view('livewire.stock.stock-detail');
    }
}
