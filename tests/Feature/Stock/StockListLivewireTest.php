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

    public function test_member_can_view_own_stocks(): void
    {
        $user = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Kertas Milik Saya', 'sku' => 'MY-1', 'price' => 1500]);
        Stock::factory()->create(['user_id' => $other->id, 'name' => 'Barang Orang Lain']);

        $this->actingAs($user)
            ->get('/stocks')
            ->assertOk()
            ->assertSee('Kertas Milik Saya')
            ->assertSee('SKU: MY-1')
            ->assertSee('Rp 1.500')
            ->assertDontSee('Barang Orang Lain');
    }

    public function test_empty_state(): void
    {
        $this->actingAs(User::factory()->member()->create());

        Livewire::test(StockList::class)->assertSee('Belum ada stok barang ditemukan');
    }

    public function test_create_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->assertSet('showFormModal', true)
            ->assertSee('Tersedia', false)
            ->set('formName', '')
            ->set('formQuantity', -1)
            ->set('formUnit', '')
            ->set('formPrice', -5)
            ->set('formMinStock', 'abc')
            ->call('saveStock')
            ->assertHasErrors(['formName', 'formQuantity', 'formUnit', 'formPrice', 'formMinStock']);

        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', 'Tinta Printer')
            ->set('formSku', 'ATK-002')
            ->set('formDescription', '**Hitam** 100ml')
            ->set('formQuantity', 3)
            ->set('formUnit', 'botol')
            ->set('formPrice', '')
            ->set('formMinStock', 5)
            ->assertSee('Stok Menipis')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Tinta Printer',
            'sku' => 'ATK-002',
            'quantity' => 3,
            'unit' => 'botol',
            'price' => null,
            'status' => 'low_stock',
        ]);
    }

    public function test_edit_stock_success(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Nama Lama', 'quantity' => 1, 'min_stock' => 5, 'price' => 100]);
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertSet('editingStockId', $stock->id)
            ->assertSet('formName', 'Nama Lama')
            ->assertSet('formQuantity', 1)
            ->set('formName', 'Nama Baru')
            ->set('formQuantity', 50)
            ->set('formPrice', 2500)
            ->call('saveStock')
            ->assertSet('showFormModal', false);

        $stock->refresh();
        $this->assertSame('Nama Baru', $stock->name);
        $this->assertSame(50, $stock->quantity);
        $this->assertSame('available', $stock->status);
    }

    public function test_member_cannot_edit_or_adjust_other_users_stock(): void
    {
        $owner = User::factory()->member()->create();
        $intruder = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id, 'quantity' => 10]);
        $this->actingAs($intruder);

        Livewire::test(StockList::class)->call('openEditModal', $stock->id)->assertForbidden();
        Livewire::test(StockList::class)->call('adjustQuantity', $stock->id, 1)->assertForbidden();
        Livewire::test(StockList::class)->call('deleteStock', $stock->id)->assertForbidden();

        $this->assertSame(10, $stock->fresh()->quantity);
    }

    public function test_adjust_quantity_syncs_status(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 6, 'min_stock' => 5, 'status' => 'available']);
        $this->actingAs($user);

        $component = Livewire::test(StockList::class)->call('adjustQuantity', $stock->id, -1);
        $this->assertSame('low_stock', $stock->fresh()->status);

        $component->call('adjustQuantity', $stock->id, -5);
        $this->assertSame(0, $stock->fresh()->quantity);
        $this->assertSame('out_of_stock', $stock->fresh()->status);

        $component->call('adjustQuantity', $stock->id, -1);
        $this->assertSame(0, $stock->fresh()->quantity);

        $component->call('adjustQuantity', $stock->id, 10);
        $this->assertSame('available', $stock->fresh()->status);
    }

    public function test_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        Livewire::test(StockList::class)->call('deleteStock', $stock->id);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_confirm_and_cancel_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('confirmDelete', $stock->id)
            ->assertSet('confirmingStockDeletionId', $stock->id)
            ->call('cancelDelete')
            ->assertSet('confirmingStockDeletionId', null)
            ->call('deleteStock')
            ->call('confirmDelete', $stock->id)
            ->call('deleteStock')
            ->assertSet('confirmingStockDeletionId', null);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_search_and_status_filter(): void
    {
        $user = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Kabel HDMI', 'sku' => 'ELK-9', 'description' => null, 'status' => 'available']);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Mouse Wireless', 'sku' => 'ELK-1', 'description' => null, 'status' => 'out_of_stock']);
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->set('search', 'HDMI')
            ->assertSee('Kabel HDMI')
            ->assertDontSee('Mouse Wireless')
            ->set('search', 'ELK-1')
            ->assertSee('Mouse Wireless')
            ->assertDontSee('Kabel HDMI')
            ->set('search', '')
            ->set('statusFilter', 'out_of_stock')
            ->assertSee('Mouse Wireless')
            ->assertDontSee('Kabel HDMI');
    }

    public function test_export_excel(): void
    {
        $user = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Exportable Stock']);
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    private function makeUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $tempPath = tempnam(sys_get_temp_dir(), 'test_upload_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);
        $content = file_get_contents($tempPath);
        @unlink($tempPath);

        return UploadedFile::fake()->createWithContent('stocks.xlsx', $content);
    }

    public function test_import_excel(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openImportModal')
            ->call('importExcel')
            ->assertHasErrors(['importFile']);

        $upload = $this->makeUpload([
            ['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock'],
            [1, 'Imported Excel Stock', 'IMP-1', 'Excel Desc', 12, 'box', 7000, 3],
            [2, 'Broken Row', null, null, 'abc', null, null, null],
        ]);

        Livewire::test(StockList::class)
            ->call('openImportModal')
            ->assertSet('showImportModal', true)
            ->set('importFile', $upload)
            ->call('importExcel')
            ->assertSet('showImportModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Imported Excel Stock',
            'sku' => 'IMP-1',
            'quantity' => 12,
            'status' => 'available',
        ]);
        $this->assertDatabaseMissing('stocks', ['name' => 'Broken Row']);

        Livewire::test(StockList::class)
            ->set('importFile', $this->makeUpload([['No', 'Name'], [1, 'Clean Row']]))
            ->call('importExcel')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'Clean Row', 'quantity' => 0]);
    }

    public function test_ai_generation_and_saving(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $mockAiClient = $this->createStub(AiClientInterface::class);
        $mockAiClient->method('generateStocks')->willReturn([
            new GeneratedStockItem('AI Item 1', 'AI-1', 'Desc 1', 0, 'pcs', 10000.0, 2),
            new GeneratedStockItem('AI Item 2', null, null, 30, 'kg', null, 5),
        ]);
        $this->app->instance(AiClientInterface::class, $mockAiClient);

        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', '')
            ->set('aiCount', 0)
            ->call('generateWithAi')
            ->assertHasErrors(['aiPrompt', 'aiCount']);

        $component = Livewire::test(StockList::class)
            ->call('openAiModal')
            ->assertSet('showAiModal', true)
            ->set('aiPrompt', 'Toko alat tulis')
            ->set('aiCount', 2)
            ->call('generateWithAi')
            ->assertCount('aiGeneratedItems', 2)
            ->assertCount('aiSelectedIndices', 2)
            ->assertSee('AI Item 1')
            ->assertSee('Rp 10.000');

        $component->set('aiSelectedIndices', [])
            ->call('saveAiGenerated')
            ->assertSet('aiErrorMessage', 'Pilih minimal satu item stok yang ingin disimpan.');

        $component->set('aiSelectedIndices', [0, 1, 99])
            ->call('saveAiGenerated')
            ->assertSet('showAiModal', false);

        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'AI Item 1', 'sku' => 'AI-1', 'status' => 'out_of_stock']);
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'AI Item 2', 'unit' => 'kg', 'status' => 'available']);
        $this->assertDatabaseCount('stocks', 2);
    }

    public function test_ai_generation_handles_exception(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $mockAiClient = $this->createStub(AiClientInterface::class);
        $mockAiClient->method('generateStocks')->willThrowException(new \RuntimeException('API quota exceeded'));
        $this->app->instance(AiClientInterface::class, $mockAiClient);

        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', 'Error test')
            ->call('generateWithAi')
            ->assertSet('aiErrorMessage', 'API quota exceeded');
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
            ->assertSet('showAiModal', false)
            ->call('confirmDelete', 1)
            ->assertSee('Konfirmasi Hapus Barang');
    }
}
