<?php

namespace Tests\Unit\Models;

use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_stock_casts(): void
    {
        $stock = Stock::factory()->create(['quantity' => '7', 'min_stock' => '3', 'price' => 1500.5]);

        $this->assertSame(7, $stock->quantity);
        $this->assertSame(3, $stock->min_stock);
        $this->assertSame('1500.50', $stock->price);
    }

    public function test_stock_factory_states(): void
    {
        $default = Stock::factory()->create();
        $this->assertEquals('available', $default->status);

        $low = Stock::factory()->lowStock()->create();
        $this->assertEquals('low_stock', $low->status);
        $this->assertEquals(3, $low->quantity);

        $out = Stock::factory()->outOfStock()->create();
        $this->assertEquals('out_of_stock', $out->status);
        $this->assertEquals(0, $out->quantity);
    }

    public function test_user_has_many_stocks(): void
    {
        $user = User::factory()->create();
        Stock::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->stocks);
    }

    public function test_cover_image_url_returns_correct_url_or_null(): void
    {
        $stockWithoutCover = Stock::factory()->create(['cover_image_path' => null]);
        $this->assertNull($stockWithoutCover->coverImageUrl());

        Storage::fake('public');
        Storage::disk('public')->put('stock-covers/sample.png', 'content');

        $stockWithCover = Stock::factory()->create(['cover_image_path' => 'stock-covers/sample.png']);
        $this->assertNotNull($stockWithCover->coverImageUrl());
        $this->assertStringContainsString('stock-covers/sample.png', $stockWithCover->coverImageUrl());
    }
}
