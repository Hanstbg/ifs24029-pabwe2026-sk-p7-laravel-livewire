<?php

namespace App\Features\Ai\DTO;

final class GeneratedStockItem
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $sku = null,
        public readonly ?string $description = null,
        public readonly int $quantity = 0,
        public readonly string $unit = 'pcs',
        public readonly ?float $price = null,
        public readonly int $minStock = 5,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? 'Tanpa nama'),
            sku: isset($data['sku']) ? (string) $data['sku'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            quantity: max(0, (int) ($data['quantity'] ?? 0)),
            unit: (string) ($data['unit'] ?? 'pcs') ?: 'pcs',
            price: isset($data['price']) ? (float) $data['price'] : null,
            minStock: max(0, (int) ($data['min_stock'] ?? 5)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'price' => $this->price,
            'min_stock' => $this->minStock,
        ];
    }
}
