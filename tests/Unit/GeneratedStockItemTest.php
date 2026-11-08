<?php

namespace Tests\Unit;

use App\Features\Ai\DTO\GeneratedStockItem;
use PHPUnit\Framework\TestCase;

class GeneratedStockItemTest extends TestCase
{
    public function test_from_array_full(): void
    {
        $item = GeneratedStockItem::fromArray([
            'name' => 'A', 'sku' => 'S', 'description' => 'D', 'quantity' => '4', 'unit' => 'box', 'price' => '2.5', 'min_stock' => 1,
        ]);

        $this->assertSame(['name' => 'A', 'sku' => 'S', 'description' => 'D', 'quantity' => 4, 'unit' => 'box', 'price' => 2.5, 'min_stock' => 1], $item->toArray());
    }

    public function test_from_array_defaults(): void
    {
        $item = GeneratedStockItem::fromArray(['quantity' => -3, 'unit' => '']);

        $this->assertSame('Tanpa nama', $item->name);
        $this->assertSame(0, $item->quantity);
        $this->assertSame('pcs', $item->unit);
        $this->assertNull($item->price);
        $this->assertSame(5, $item->minStock);
    }
}
