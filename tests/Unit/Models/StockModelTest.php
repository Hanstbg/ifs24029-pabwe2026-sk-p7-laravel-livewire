<?php

namespace Tests\Unit\Models;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $stock->user);
        $this->assertEquals($user->id, $stock->user->id);
    }

    public function test_stock_casts_and_defaults(): void
    {
        $user = User::factory()->create();
        $stock = Stock::create(['user_id' => $user->id, 'name' => 'Barang Baru', 'price' => '1500.5']);
        $stock->refresh();

        $this->assertSame(0, $stock->quantity);
        $this->assertSame(5, $stock->min_stock);
        $this->assertSame('pcs', $stock->unit);
        $this->assertSame('out_of_stock', $stock->status);
        $this->assertSame('1500.50', $stock->price);
    }

    public function test_stock_factory_states(): void
    {
        $low = Stock::factory()->lowStock()->create();
        $out = Stock::factory()->outOfStock()->create();

        $this->assertSame(StockStatus::LOW_STOCK, $low->statusEnum());
        $this->assertSame(StockStatus::OUT_OF_STOCK, $out->statusEnum());
    }

    public function test_status_enum_falls_back_to_quantity_for_unknown_status(): void
    {
        $stock = Stock::factory()->make(['status' => 'unknown', 'quantity' => 2, 'min_stock' => 5]);

        $this->assertSame(StockStatus::LOW_STOCK, $stock->statusEnum());
    }

    public function test_total_value(): void
    {
        $stock = Stock::factory()->make(['quantity' => 4, 'price' => 2500]);
        $this->assertEquals(10000.0, $stock->totalValue());

        $noPrice = Stock::factory()->make(['quantity' => 4, 'price' => null]);
        $this->assertEquals(0.0, $noPrice->totalValue());
    }

    public function test_cover_image_url(): void
    {
        Storage::fake('public');

        $stock = Stock::factory()->create(['cover_image_path' => null]);
        $this->assertNull($stock->coverImageUrl());

        $stock->cover_image_path = 'stock-covers/missing.jpg';
        $this->assertNull($stock->coverImageUrl());

        $path = UploadedFile::fake()->image('cover.jpg')->store('stock-covers', 'public');
        $stock->cover_image_path = $path;
        $this->assertStringContainsString($path, (string) $stock->coverImageUrl());
    }
}
