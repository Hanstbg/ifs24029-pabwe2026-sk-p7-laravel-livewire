<?php

namespace Tests\Unit;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockService::class);
        $this->user = User::factory()->create();
    }

    public function test_create_update_find_list(): void
    {
        $stock = $this->service->create($this->user, ['name' => 'A', 'quantity' => 10, 'min_stock' => 5, 'unit' => '']);
        $this->assertSame('pcs', $stock->unit);
        $this->assertSame('available', $stock->status->value);

        $stock = $this->service->update($stock, ['name' => 'B', 'quantity' => 0, 'min_stock' => 5, 'unit' => 'box']);
        $this->assertSame('out_of_stock', $stock->status->value);

        $this->assertTrue($this->service->find($this->user, $stock->id)->is($stock));
        $this->assertSame(1, $this->service->list($this->user)->total());
        $this->assertSame(0, $this->service->list($this->user, 'zzz')->total());
    }

    public function test_adjust_quantity(): void
    {
        $stock = Stock::factory()->create(['user_id' => $this->user->id, 'quantity' => 2, 'min_stock' => 5]);

        $this->assertSame(0, $this->service->adjustQuantity($stock, -10)->quantity);
        $this->assertSame(30, $this->service->adjustQuantity($stock, 30)->quantity);
    }

    public function test_cover_and_delete(): void
    {
        Storage::fake('public');
        $stock = Stock::factory()->create(['user_id' => $this->user->id]);

        $stock = $this->service->setCover($stock, UploadedFile::fake()->image('a.jpg'));
        $path = $stock->cover_image_path;
        Storage::disk('public')->assertExists($path);

        $this->service->delete($stock);
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_delete_without_cover(): void
    {
        $stock = Stock::factory()->create(['user_id' => $this->user->id, 'cover_image_path' => null]);

        $this->service->delete($stock);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_bulk_create(): void
    {
        $count = $this->service->bulkCreate($this->user, [
            ['name' => 'X'],
            ['name' => 'Y', 'sku' => 'S', 'description' => 'd', 'quantity' => 3, 'unit' => 'kg', 'price' => 10, 'min_stock' => 1],
        ]);

        $this->assertSame(2, $count);
        $this->assertDatabaseHas('stocks', ['name' => 'X', 'quantity' => 0, 'status' => 'out_of_stock']);
        $this->assertDatabaseHas('stocks', ['name' => 'Y', 'quantity' => 3, 'status' => 'low_stock']);
    }

    public function test_repository_all_for_user(): void
    {
        Stock::factory()->count(2)->create(['user_id' => $this->user->id]);
        Stock::factory()->create();

        $this->assertCount(2, app(\App\Features\Stock\Contracts\StockRepositoryInterface::class)->allForUser($this->user->id));
    }
}
