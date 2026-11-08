<?php

namespace App\Features\Stock\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StockService
{
    public function __construct(private StockRepositoryInterface $repository) {}

    public function list(User $user, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->repository->paginateForUser($user->id, $search, $status);
    }

    public function find(User $user, int $id): Stock
    {
        return $this->repository->findForUser($user->id, $id);
    }

    public function create(User $user, array $data): Stock
    {
        $data['user_id'] = $user->id;
        $data['unit'] = $data['unit'] ?: 'pcs';
        $data['status'] = StockStatus::fromQuantity((int) $data['quantity'], (int) $data['min_stock'])->value;

        return $this->repository->create($data);
    }

    public function update(Stock $stock, array $data): Stock
    {
        $data['unit'] = $data['unit'] ?: 'pcs';
        $data['status'] = StockStatus::fromQuantity((int) $data['quantity'], (int) $data['min_stock'])->value;

        return $this->repository->update($stock, $data);
    }

    public function delete(Stock $stock): void
    {
        if ($stock->cover_image_path) {
            Storage::disk('public')->delete($stock->cover_image_path);
        }

        $this->repository->delete($stock);
    }

    public function adjustQuantity(Stock $stock, int $delta): Stock
    {
        $quantity = max(0, $stock->quantity + $delta);

        return $this->repository->update($stock, [
            'quantity' => $quantity,
            'status' => StockStatus::fromQuantity($quantity, $stock->min_stock)->value,
        ]);
    }

    public function setCover(Stock $stock, UploadedFile $file): Stock
    {
        if ($stock->cover_image_path) {
            Storage::disk('public')->delete($stock->cover_image_path);
        }

        $path = $file->store('covers', 'public');

        return $this->repository->update($stock, ['cover_image_path' => $path]);
    }

    /** @param array<int, array<string, mixed>> $rows */
    public function bulkCreate(User $user, array $rows): int
    {
        foreach ($rows as $row) {
            $this->create($user, [
                'name' => $row['name'],
                'sku' => $row['sku'] ?? null,
                'description' => $row['description'] ?? null,
                'quantity' => (int) ($row['quantity'] ?? 0),
                'unit' => $row['unit'] ?? 'pcs',
                'price' => $row['price'] ?? null,
                'min_stock' => (int) ($row['min_stock'] ?? 5),
            ]);
        }

        return count($rows);
    }
}
