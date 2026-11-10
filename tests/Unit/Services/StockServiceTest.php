<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Features\Stock\Services\StockService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $service;
    protected StockRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new StockRepository();
        $this->service = new StockService($this->repository);
    }

    public function test_get_stocks_for_user(): void
    {
        $user = User::factory()->create();
        Stock::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $this->service->getStocksForUser($user->id));
    }

    public function test_create_stock_derives_status_and_defaults(): void
    {
        $user = User::factory()->create();

        $stock = $this->service->createStock($user->id, [
            'name' => 'Tinta Printer',
            'sku' => '  ',
            'quantity' => 3,
            'min_stock' => 5,
            'unit' => '',
            'price' => '',
        ]);

        $this->assertInstanceOf(Stock::class, $stock);
        $this->assertSame('Tinta Printer', $stock->name);
        $this->assertNull($stock->sku);
        $this->assertSame('pcs', $stock->unit);
        $this->assertNull($stock->price);
        $this->assertSame(StockStatus::LOW_STOCK->value, $stock->status);

        $minimal = $this->service->createStock($user->id, ['name' => 'Kosong']);
        $this->assertSame(0, $minimal->quantity);
        $this->assertSame(5, $minimal->min_stock);
        $this->assertSame(StockStatus::OUT_OF_STOCK->value, $minimal->status);
    }

    public function test_update_stock_resyncs_status(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Lama', 'quantity' => 20, 'min_stock' => 5]);

        $this->assertTrue($this->service->updateStock($stock, [
            'name' => 'Baru',
            'sku' => 'SKU-NEW',
            'description' => 'Updated',
            'quantity' => 2,
            'unit' => 'box',
            'price' => 1000,
            'min_stock' => 4,
        ]));

        $stock->refresh();
        $this->assertSame('Baru', $stock->name);
        $this->assertSame('SKU-NEW', $stock->sku);
        $this->assertSame('box', $stock->unit);
        $this->assertSame(2, $stock->quantity);
        $this->assertSame(StockStatus::LOW_STOCK->value, $stock->status);

        // Partial update keeps existing values
        $this->service->updateStock($stock, ['min_stock' => 0]);
        $stock->refresh();
        $this->assertSame('Baru', $stock->name);
        $this->assertSame(2, $stock->quantity);
        $this->assertSame(StockStatus::AVAILABLE->value, $stock->status);

        // Blank unit falls back to pcs
        $this->service->updateStock($stock, ['unit' => ' ']);
        $this->assertSame('pcs', $stock->fresh()->unit);
    }

    public function test_adjust_quantity_and_status_sync(): void
    {
        $stock = Stock::factory()->create(['quantity' => 6, 'min_stock' => 5]);

        $this->service->adjustQuantity($stock, -1);
        $stock->refresh();
        $this->assertSame(5, $stock->quantity);
        $this->assertSame(StockStatus::LOW_STOCK->value, $stock->status);

        $this->service->adjustQuantity($stock, -10);
        $stock->refresh();
        $this->assertSame(0, $stock->quantity);
        $this->assertSame(StockStatus::OUT_OF_STOCK->value, $stock->status);

        $this->service->adjustQuantity($stock, 10);
        $stock->refresh();
        $this->assertSame(10, $stock->quantity);
        $this->assertSame(StockStatus::AVAILABLE->value, $stock->status);
    }

    public function test_cover_photo_upload_replace_and_delete(): void
    {
        Storage::fake('public');
        $stock = Stock::factory()->create();

        $first = $this->service->updateCoverPhoto($stock, UploadedFile::fake()->image('a.jpg'));
        Storage::disk('public')->assertExists($first);

        $second = $this->service->updateCoverPhoto($stock->fresh(), UploadedFile::fake()->image('b.jpg'));
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->assertTrue($this->service->deleteCoverPhoto($stock->fresh()));
        Storage::disk('public')->assertMissing($second);
        $this->assertNull($stock->fresh()->cover_image_path);

        // deleting when no cover exists is still fine
        $this->assertTrue($this->service->deleteCoverPhoto($stock->fresh()));
    }

    public function test_delete_stock_removes_cover(): void
    {
        Storage::fake('public');
        $stock = Stock::factory()->create();
        $path = $this->service->updateCoverPhoto($stock, UploadedFile::fake()->image('c.jpg'));

        $this->assertTrue($this->service->deleteStock($stock->fresh()));
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);

        $plain = Stock::factory()->create();
        $this->assertTrue($this->service->deleteStock($plain));
    }
}
