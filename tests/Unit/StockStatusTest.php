<?php

namespace Tests\Unit;

use App\Features\Stock\Enums\StockStatus;
use PHPUnit\Framework\TestCase;

class StockStatusTest extends TestCase
{
    public function test_from_quantity(): void
    {
        $this->assertSame(StockStatus::OutOfStock, StockStatus::fromQuantity(0, 5));
        $this->assertSame(StockStatus::LowStock, StockStatus::fromQuantity(5, 5));
        $this->assertSame(StockStatus::Available, StockStatus::fromQuantity(6, 5));
    }

    public function test_label_and_badge_for_all_cases(): void
    {
        foreach (StockStatus::cases() as $case) {
            $this->assertNotEmpty($case->label());
            $this->assertStringContainsString('bg-', $case->badgeClass());
        }
    }
}
