<?php

namespace App\Features\Stock\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class StockService
{
    public function __construct(
        protected StockRepositoryInterface $stockRepository
    ) {}

    /**
     * Get paginated stocks for the specified user.
     */
    public function getStocksForUser(int $userId, ?string $status = null, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->stockRepository->forUser($userId, $status, $search, $perPage);
    }

    /**
     * Create a new stock item for the user. Status is derived from quantity vs min_stock.
     *
     * @param array<string, mixed> $data
     */
    public function createStock(int $userId, array $data): Stock
    {
        $quantity = max(0, (int) ($data['quantity'] ?? 0));
        $minStock = max(0, (int) ($data['min_stock'] ?? 5));

        return $this->stockRepository->create([
            'user_id' => $userId,
            'name' => $data['name'],
            'sku' => $this->nullIfBlank($data['sku'] ?? null),
            'description' => $data['description'] ?? null,
            'quantity' => $quantity,
            'unit' => $this->nullIfBlank($data['unit'] ?? null) ?? 'pcs',
            'price' => $this->nullIfBlank($data['price'] ?? null),
            'min_stock' => $minStock,
            'status' => StockStatus::fromQuantity($quantity, $minStock)->value,
            'cover_image_path' => $data['cover_image_path'] ?? null,
        ]);
    }

    /**
     * Update an existing stock item. Status is re-synced from quantity vs min_stock.
     *
     * @param array<string, mixed> $data
     */
    public function updateStock(Stock $stock, array $data): bool
    {
        $quantity = array_key_exists('quantity', $data) ? max(0, (int) $data['quantity']) : (int) $stock->quantity;
        $minStock = array_key_exists('min_stock', $data) ? max(0, (int) $data['min_stock']) : (int) $stock->min_stock;

        return $this->stockRepository->update($stock, [
            'name' => $data['name'] ?? $stock->name,
            'sku' => array_key_exists('sku', $data) ? $this->nullIfBlank($data['sku']) : $stock->sku,
            'description' => array_key_exists('description', $data) ? $data['description'] : $stock->description,
            'quantity' => $quantity,
            'unit' => array_key_exists('unit', $data) ? ($this->nullIfBlank($data['unit']) ?? 'pcs') : $stock->unit,
            'price' => array_key_exists('price', $data) ? $this->nullIfBlank($data['price']) : $stock->price,
            'min_stock' => $minStock,
            'status' => StockStatus::fromQuantity($quantity, $minStock)->value,
            'cover_image_path' => array_key_exists('cover_image_path', $data) ? $data['cover_image_path'] : $stock->cover_image_path,
        ]);
    }

    /**
     * Adjust the quantity by a delta (positive = add, negative = reduce) and sync status.
     * Quantity never goes below zero.
     */
    public function adjustQuantity(Stock $stock, int $delta): bool
    {
        $newQuantity = max(0, (int) $stock->quantity + $delta);

        return $this->stockRepository->update($stock, [
            'quantity' => $newQuantity,
            'status' => StockStatus::fromQuantity($newQuantity, (int) $stock->min_stock)->value,
        ]);
    }

    /**
     * Update or upload a cover photo for the stock.
     */
    public function updateCoverPhoto(Stock $stock, mixed $file): string
    {
        if ($stock->cover_image_path && Storage::disk('public')->exists($stock->cover_image_path)) {
            Storage::disk('public')->delete($stock->cover_image_path);
        }

        $path = $file->store('stock-covers', 'public');
        $this->stockRepository->update($stock, ['cover_image_path' => $path]);

        return $path;
    }

    /**
     * Delete the cover photo of the stock.
     */
    public function deleteCoverPhoto(Stock $stock): bool
    {
        if ($stock->cover_image_path && Storage::disk('public')->exists($stock->cover_image_path)) {
            Storage::disk('public')->delete($stock->cover_image_path);
        }

        return $this->stockRepository->update($stock, ['cover_image_path' => null]);
    }

    /**
     * Delete a stock item and its cover photo if exists.
     */
    public function deleteStock(Stock $stock): bool
    {
        if ($stock->cover_image_path && Storage::disk('public')->exists($stock->cover_image_path)) {
            Storage::disk('public')->delete($stock->cover_image_path);
        }

        return $this->stockRepository->delete($stock);
    }

    private function nullIfBlank(mixed $value): mixed
    {
        return ($value === null || (is_string($value) && trim($value) === '')) ? null : $value;
    }
}
