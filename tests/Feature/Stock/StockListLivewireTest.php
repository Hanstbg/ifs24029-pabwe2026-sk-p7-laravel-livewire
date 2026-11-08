<?php

namespace Tests\Feature\Stock;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\DTO\GeneratedStockItem;
use App\Features\Stock\Livewire\StockList;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockExcelService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class StockListLivewireTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_lists_search_and_filter(): void
    {
        Stock::factory()->create(['user_id' => $this->user->id, 'name' => 'Pensil', 'quantity' => 50, 'status' => 'available']);
        Stock::factory()->create(['user_id' => $this->user->id, 'name' => 'Penghapus', 'quantity' => 0, 'status' => 'out_of_stock']);
        Stock::factory()->create(['name' => 'Milik Orang Lain']);

        Livewire::test(StockList::class)
            ->assertSee('Pensil')->assertSee('Penghapus')->assertDontSee('Milik Orang Lain')
            ->set('search', 'Pensil')->assertSee('Pensil')->assertDontSee('Penghapus')
            ->set('search', '')->set('statusFilter', 'out_of_stock')
            ->assertSee('Penghapus')->assertDontSee('Pensil');
    }

    public function test_empty_state(): void
    {
        Livewire::test(StockList::class)->assertSee('Belum ada stok.');
    }

    public function test_create_stock(): void
    {
        Livewire::test(StockList::class)
            ->call('create')->assertSet('showForm', true)
            ->set('name', 'Buku')->set('sku', 'BK-1')->set('quantity', 3)->set('unit', '')
            ->set('price', '15000')->set('min_stock', 5)
            ->call('save')->assertSet('showForm', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $this->user->id, 'name' => 'Buku', 'unit' => 'pcs', 'status' => 'low_stock',
        ]);
    }

    public function test_create_validation(): void
    {
        Livewire::test(StockList::class)->call('create')->set('name', '')->set('quantity', -1)
            ->call('save')->assertHasErrors(['name', 'quantity']);
    }

    public function test_edit_and_update_stock(): void
    {
        $stock = Stock::factory()->create(['user_id' => $this->user->id, 'sku' => null, 'price' => null, 'description' => null]);

        Livewire::test(StockList::class)
            ->call('edit', $stock->id)->assertSet('editingId', $stock->id)
            ->set('name', 'Baru')->set('quantity', 100)->set('price', '')->set('sku', '')
            ->call('save');

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'name' => 'Baru', 'status' => 'available', 'price' => null]);
    }

    public function test_cannot_edit_other_users_stock(): void
    {
        $other = Stock::factory()->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(StockList::class)->call('edit', $other->id);
    }

    public function test_cancel_and_delete(): void
    {
        $stock = Stock::factory()->create(['user_id' => $this->user->id]);

        Livewire::test(StockList::class)
            ->call('create')->call('cancel')->assertSet('showForm', false)
            ->call('delete', $stock->id);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_adjust_quantity_syncs_status(): void
    {
        $stock = Stock::factory()->create(['user_id' => $this->user->id, 'quantity' => 1, 'min_stock' => 5, 'status' => 'low_stock']);

        Livewire::test(StockList::class)->call('adjust', $stock->id, -1);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 0, 'status' => 'out_of_stock']);

        Livewire::test(StockList::class)->call('adjust', $stock->id, -1);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 0]);

        Livewire::test(StockList::class)->call('adjust', $stock->id, 20);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 20, 'status' => 'available']);
    }

    public function test_export_excel(): void
    {
        Stock::factory()->count(2)->create(['user_id' => $this->user->id]);

        Livewire::test(StockList::class)->call('exportExcel')->assertFileDownloaded('stok.xlsx');
    }

    public function test_import_excel(): void
    {
        $content = app(StockExcelService::class)->export(
            Stock::factory()->count(2)->make(['user_id' => $this->user->id])
        );
        $file = UploadedFile::fake()->createWithContent('stok.xlsx', $content);

        Livewire::test(StockList::class)->set('importFile', $file)->call('import')->assertSet('importFile', null);

        $this->assertSame(2, Stock::where('user_id', $this->user->id)->count());
    }

    public function test_import_requires_file(): void
    {
        Livewire::test(StockList::class)->call('import')->assertHasErrors('importFile');
    }

    public function test_ai_generate_and_save(): void
    {
        $stub = $this->createStub(AiClientInterface::class);
        $stub->method('generate')->willReturn([
            new GeneratedStockItem('Spidol', 'SP-1', 'Spidol hitam', 10, 'pcs', 5000.0, 2),
            new GeneratedStockItem('Kertas'),
        ]);
        $this->app->instance(AiClientInterface::class, $stub);

        Livewire::test(StockList::class)
            ->set('aiPrompt', 'alat tulis')->call('generateAi')
            ->assertSet('aiError', null)->assertSee('Spidol')
            ->set('aiSelected', [0, 5])->call('saveAi')
            ->assertSet('aiItems', []);

        $this->assertDatabaseHas('stocks', ['user_id' => $this->user->id, 'name' => 'Spidol']);
        $this->assertDatabaseMissing('stocks', ['name' => 'Kertas']);
    }

    public function test_ai_error_is_shown(): void
    {
        $stub = $this->createStub(AiClientInterface::class);
        $stub->method('generate')->willThrowException(new \RuntimeException('Gagal AI'));
        $this->app->instance(AiClientInterface::class, $stub);

        Livewire::test(StockList::class)->set('aiPrompt', 'x')->call('generateAi')->assertSet('aiError', 'Gagal AI');
    }

    public function test_ai_prompt_required(): void
    {
        Livewire::test(StockList::class)->call('generateAi')->assertHasErrors('aiPrompt');
    }
}
