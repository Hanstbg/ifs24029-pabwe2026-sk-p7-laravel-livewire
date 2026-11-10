<?php

namespace Tests\Unit\DTO;

use App\Features\Ai\DTO\GeneratedStockItem;
use PHPUnit\Framework\TestCase;

class GeneratedStockItemTest extends TestCase
{
    public function test_dto_properties_and_to_array(): void
    {
        $dto = new GeneratedStockItem(
            name: 'Kertas HVS A4',
            sku: 'ATK-001',
            description: 'Kertas 80gsm',
            quantity: 40,
            unit: 'rim',
            price: '52000',
            minStock: 10,
        );

        $this->assertEquals('Kertas HVS A4', $dto->name);
        $this->assertEquals('ATK-001', $dto->sku);
        $this->assertEquals('rim', $dto->unit);
        $this->assertEquals(10, $dto->minStock);

        $this->assertEquals([
            'name' => 'Kertas HVS A4',
            'sku' => 'ATK-001',
            'description' => 'Kertas 80gsm',
            'quantity' => 40,
            'unit' => 'rim',
            'price' => '52000',
            'min_stock' => 10,
        ], $dto->toArray());
    }

    public function test_dto_defaults(): void
    {
        $dto = new GeneratedStockItem('Item Saja');

        $this->assertNull($dto->sku);
        $this->assertNull($dto->description);
        $this->assertSame(0, $dto->quantity);
        $this->assertSame('pcs', $dto->unit);
        $this->assertNull($dto->price);
        $this->assertSame(5, $dto->minStock);
    }
}
