<?php

namespace Tests\Unit\DTO;

use App\Features\Ai\DTO\GeneratedStockItem;
use PHPUnit\Framework\TestCase;

class GeneratedStockItemTest extends TestCase
{
    public function test_dto_properties_and_to_array(): void
    {
        $dto = new GeneratedStockItem(
            name: 'Kertas HVS',
            sku: 'ATK-001',
            description: 'A4 80gr',
            quantity: 10,
            unit: 'rim',
            price: 55000.0,
            minStock: 3,
        );

        $this->assertSame('Kertas HVS', $dto->name);
        $this->assertSame([
            'name' => 'Kertas HVS',
            'sku' => 'ATK-001',
            'description' => 'A4 80gr',
            'quantity' => 10,
            'unit' => 'rim',
            'price' => 55000.0,
            'min_stock' => 3,
        ], $dto->toArray());
    }

    public function test_dto_defaults(): void
    {
        $dto = new GeneratedStockItem('Pulpen');

        $this->assertSame([
            'name' => 'Pulpen',
            'sku' => null,
            'description' => null,
            'quantity' => 0,
            'unit' => 'pcs',
            'price' => null,
            'min_stock' => 5,
        ], $dto->toArray());
    }
}
