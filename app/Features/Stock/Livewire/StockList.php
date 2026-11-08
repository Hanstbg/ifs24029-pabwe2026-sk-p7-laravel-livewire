<?php

namespace App\Features\Stock\Livewire;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Stock\Services\StockExcelService;
use App\Features\Stock\Services\StockService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

#[Layout('components.layouts.app')]
#[Title('Stok Barang')]
class StockList extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public $name = '';

    public $sku = '';

    public $description = '';

    public $quantity = 0;

    public $unit = 'pcs';

    public $price = '';

    public $min_stock = 5;

    public $importFile = null;

    public ?string $message = null;

    public string $aiPrompt = '';

    public array $aiItems = [];

    public array $aiSelected = [];

    public ?string $aiError = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id, StockService $service): void
    {
        $stock = $service->find(auth()->user(), $id);

        $this->editingId = $stock->id;
        $this->name = $stock->name;
        $this->sku = $stock->sku ?? '';
        $this->description = $stock->description ?? '';
        $this->quantity = $stock->quantity;
        $this->unit = $stock->unit;
        $this->price = $stock->price ?? '';
        $this->min_stock = $stock->min_stock;
        $this->showForm = true;
    }

    public function save(StockService $service): void
    {
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

        if ($this->editingId) {
            $service->update($service->find(auth()->user(), $this->editingId), $data);
            $this->message = 'Stok berhasil diperbarui.';
        } else {
            $service->create(auth()->user(), $data);
            $this->message = 'Stok berhasil ditambahkan.';
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id, StockService $service): void
    {
        $service->delete($service->find(auth()->user(), $id));
        $this->message = 'Stok berhasil dihapus.';
    }

    public function adjust(int $id, int $delta, StockService $service): void
    {
        $service->adjustQuantity($service->find(auth()->user(), $id), $delta);
    }

    public function exportExcel(StockService $service, StockExcelService $excel)
    {
        $content = $excel->export(app(\App\Features\Stock\Contracts\StockRepositoryInterface::class)->allForUser(auth()->id()));

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, 'stok.xlsx');
    }

    public function import(StockService $service, StockExcelService $excel): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx', 'max:2048']]);

        $rows = $excel->parse($this->importFile->getRealPath());
        $count = $service->bulkCreate(auth()->user(), $rows);

        $this->importFile = null;
        $this->message = "{$count} stok berhasil diimpor.";
    }

    public function generateAi(AiClientInterface $ai): void
    {
        $this->validate(['aiPrompt' => ['required', 'string', 'max:500']]);

        $this->aiError = null;
        $this->aiItems = [];
        $this->aiSelected = [];

        try {
            $items = $ai->generate($this->aiPrompt, 5);
        } catch (Throwable $e) {
            $this->aiError = $e->getMessage();

            return;
        }

        $this->aiItems = array_map(fn ($item) => $item->toArray(), $items);
        $this->aiSelected = array_keys($this->aiItems);
    }

    public function saveAi(StockService $service): void
    {
        $rows = [];
        foreach ($this->aiSelected as $index) {
            if (isset($this->aiItems[$index])) {
                $item = $this->aiItems[$index];
                $item['quantity'] = $item['quantity'] ?? 0;
                $rows[] = $item;
            }
        }

        $count = $service->bulkCreate(auth()->user(), $rows);

        $this->aiItems = [];
        $this->aiSelected = [];
        $this->aiPrompt = '';
        $this->message = "{$count} stok hasil AI disimpan.";
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'sku', 'description', 'quantity', 'unit', 'price', 'min_stock']);
        $this->resetErrorBag();
    }

    public function render(StockService $service)
    {
        return view('livewire.stock.stock-list', [
            'stocks' => $service->list(auth()->user(), $this->search ?: null, $this->statusFilter ?: null),
        ]);
    }
}
