<?php

namespace Tests\Feature\Stock;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\DTO\GeneratedStockItem;
use App\Features\Stock\Livewire\StockList;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StockListLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_stocks(): void
    {
        $this->get('/stocks')->assertRedirect(route('login'));
    }

    public function test_member_can_view_own_stocks_only(): void
    {
        $user = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Kertas Milik Member']);

        $otherUser = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $otherUser->id, 'name' => 'Barang Rahasia Orang Lain']);

        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->assertSee('Kertas Milik Member')
            ->assertDontSee('Barang Rahasia Orang Lain');
    }

    public function test_create_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        // Validation error
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', '')
            ->set('formQuantity', -1)
            ->set('formMinStock', 'abc')
            ->set('formUnit', '')
            ->call('saveStock')
            ->assertHasErrors(['formName', 'formQuantity', 'formMinStock', 'formUnit']);

        // Success: status derived from quantity vs min_stock
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->assertSet('showFormModal', true)
            ->set('formName', 'Stok Baru')
            ->set('formSku', 'SKU-NEW')
            ->set('formDescription', 'Deskripsi **barang**')
            ->set('formQuantity', 3)
            ->set('formUnit', 'box')
            ->set('formPrice', 15000)
            ->set('formMinStock', 5)
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Stok Baru',
            'sku' => 'SKU-NEW',
            'description' => 'Deskripsi **barang**',
            'quantity' => 3,
            'unit' => 'box',
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);
    }

    public function test_edit_stock_success(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nama Lama',
            'price' => 2500,
        ]);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertSet('editingStockId', $stock->id)
            ->assertSet('formName', 'Nama Lama')
            ->assertSet('formPrice', 2500)
            ->set('formName', 'Nama Telah Diedit')
            ->set('formQuantity', 0)
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'name' => 'Nama Telah Diedit',
            'quantity' => 0,
            'status' => 'out_of_stock',
        ]);
    }

    public function test_edit_modal_handles_stock_without_price(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id, 'price' => null]);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertSet('formPrice', null)
            ->call('saveStock')
            ->assertHasNoErrors();
    }

    public function test_cannot_edit_adjust_or_delete_other_users_stock(): void
    {
        $user = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        $foreign = Stock::factory()->create(['user_id' => $other->id, 'quantity' => 10]);

        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openEditModal', $foreign->id)
            ->assertForbidden();

        Livewire::test(StockList::class)
            ->call('adjustQuantity', $foreign->id, 1)
            ->assertForbidden();

        Livewire::test(StockList::class)
            ->call('deleteStock', $foreign->id)
            ->assertForbidden();

        $this->assertDatabaseHas('stocks', ['id' => $foreign->id, 'quantity' => 10]);
    }

    public function test_adjust_quantity_updates_status(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'quantity' => 6,
            'min_stock' => 5,
            'status' => 'available',
        ]);

        $component = Livewire::test(StockList::class);

        // 6 -> 5 : low_stock (quantity <= min_stock)
        $component->call('adjustQuantity', $stock->id, -1);
        $this->assertEquals(5, $stock->fresh()->quantity);
        $this->assertEquals('low_stock', $stock->fresh()->status);

        // 5 -> 0 : out_of_stock
        $component->call('adjustQuantity', $stock->id, -5);
        $this->assertEquals(0, $stock->fresh()->quantity);
        $this->assertEquals('out_of_stock', $stock->fresh()->status);

        // never below zero
        $component->call('adjustQuantity', $stock->id, -3);
        $this->assertEquals(0, $stock->fresh()->quantity);

        // 0 -> 20 : available
        $component->call('adjustQuantity', $stock->id, 20);
        $this->assertEquals(20, $stock->fresh()->quantity);
        $this->assertEquals('available', $stock->fresh()->status);
    }

    public function test_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockList::class)
            ->call('deleteStock', $stock->id);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_confirm_and_cancel_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockList::class)
            ->assertSet('confirmingStockDeletionId', null)
            ->call('confirmDelete', $stock->id)
            ->assertSet('confirmingStockDeletionId', $stock->id)
            ->call('cancelDelete')
            ->assertSet('confirmingStockDeletionId', null)
            ->call('deleteStock') // nothing confirmed, nothing deleted
            ->call('confirmDelete', $stock->id)
            ->call('deleteStock')
            ->assertSet('confirmingStockDeletionId', null);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_search_and_status_filter(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Pulpen Hitam', 'sku' => 'ATK-777', 'status' => 'available']);
        Stock::factory()->outOfStock()->create(['user_id' => $user->id, 'name' => 'Tinta Printer', 'sku' => 'ELK-001']);

        Livewire::test(StockList::class)
            ->set('search', 'Pulpen')
            ->assertSee('Pulpen Hitam')
            ->assertDontSee('Tinta Printer')
            ->set('search', 'ELK-001') // search by SKU
            ->assertSee('Tinta Printer')
            ->assertDontSee('Pulpen Hitam')
            ->set('search', '')
            ->set('statusFilter', 'out_of_stock')
            ->assertSee('Tinta Printer')
            ->assertDontSee('Pulpen Hitam');
    }

    public function test_export_excel(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Exportable Stock']);

        Livewire::test(StockList::class)
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_import_excel(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach (['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock'] as $i => $header) {
            $sheet->setCellValue(chr(65 + $i).'1', $header);
        }
        $sheet->fromArray([1, 'Imported Excel Stock', 'IMP-001', 'Excel Desc', 2, 'box', 12500, 5], null, 'A2');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_upload_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);
        $fileContent = file_get_contents($tempPath);
        @unlink($tempPath);

        $uploadedFile = UploadedFile::fake()->createWithContent('stocks.xlsx', $fileContent);

        Livewire::test(StockList::class)
            ->call('openImportModal')
            ->assertSet('showImportModal', true)
            ->set('importFile', $uploadedFile)
            ->call('importExcel')
            ->assertSet('showImportModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Imported Excel Stock',
            'sku' => 'IMP-001',
            'description' => 'Excel Desc',
            'quantity' => 2,
            'unit' => 'box',
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);
    }

    public function test_import_requires_file(): void
    {
        $this->actingAs(User::factory()->member()->create());

        Livewire::test(StockList::class)
            ->call('importExcel')
            ->assertHasErrors(['importFile']);
    }

    public function test_ai_generation_and_saving(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $mockAiClient = $this->createStub(AiClientInterface::class);
        $mockAiClient->method('generateStocks')->willReturn([
            new GeneratedStockItem(name: 'AI Item 1', sku: 'AI-001', description: 'Desc 1', quantity: 10, unit: 'rim', price: '50000', minStock: 2),
            new GeneratedStockItem(name: 'AI Item 2'),
        ]);
        $this->app->instance(AiClientInterface::class, $mockAiClient);

        // Validation for prompt and count
        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', '')
            ->set('aiCount', 0)
            ->call('generateWithAi')
            ->assertHasErrors(['aiPrompt', 'aiCount']);

        $component = Livewire::test(StockList::class)
            ->call('openAiModal')
            ->assertSet('showAiModal', true)
            ->set('aiPrompt', 'Buatkan daftar stok ATK')
            ->set('aiCount', 2)
            ->call('generateWithAi')
            ->assertCount('aiGeneratedItems', 2)
            ->assertCount('aiSelectedIndices', 2);

        // Nothing selected
        $component->set('aiSelectedIndices', [])
            ->call('saveAiGenerated')
            ->assertSet('aiErrorMessage', 'Pilih minimal satu item stok yang ingin disimpan.');

        // A stale index is ignored, valid ones are saved
        $component->set('aiSelectedIndices', [0, 1, 99])
            ->call('saveAiGenerated')
            ->assertSet('showAiModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'AI Item 1',
            'sku' => 'AI-001',
            'quantity' => 10,
            'unit' => 'rim',
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'AI Item 2', 'status' => 'out_of_stock']);
    }

    public function test_ai_generation_handles_exception(): void
    {
        $this->actingAs(User::factory()->member()->create());

        $mockAiClient = $this->createStub(AiClientInterface::class);
        $mockAiClient->method('generateStocks')->willThrowException(new \RuntimeException('API quota exceeded'));
        $this->app->instance(AiClientInterface::class, $mockAiClient);

        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', 'Error test')
            ->call('generateWithAi')
            ->assertSet('aiErrorMessage', 'API quota exceeded')
            ->assertSet('aiIsGenerating', false);
    }

    public function test_close_modals(): void
    {
        $this->actingAs(User::factory()->member()->create());

        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->call('closeFormModal')
            ->assertSet('showFormModal', false)
            ->call('openImportModal')
            ->call('closeImportModal')
            ->assertSet('showImportModal', false)
            ->call('openAiModal')
            ->call('closeAiModal')
            ->assertSet('showAiModal', false);
    }
}
