<?php

namespace Tests\Feature\Stock;

use App\Features\Stock\Livewire\StockDetail;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StockDetailLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_stock_detail(): void
    {
        $stock = Stock::factory()->create();

        $this->get(route('stocks.show', $stock->id))->assertRedirect(route('login'));
    }

    public function test_member_cannot_access_other_member_stock(): void
    {
        $owner = User::factory()->member()->create();
        $otherMember = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherMember)
            ->get(route('stocks.show', $stock->id))
            ->assertStatus(403);
    }

    public function test_member_can_view_own_stock_detail(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Kertas HVS A4',
            'sku' => 'ATK-001',
            'description' => '# Deskripsi Markdown',
            'quantity' => 40,
            'unit' => 'rim',
            'price' => 55000,
            'min_stock' => 10,
            'status' => 'available',
        ]);

        $this->actingAs($user)
            ->get(route('stocks.show', $stock->id))
            ->assertOk()
            ->assertSee('Kertas HVS A4')
            ->assertSee('SKU: ATK-001')
            ->assertSee('Rp 2.200.000')
            ->assertSee('Sesuaikan Quantity');

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertSet('name', 'Kertas HVS A4')
            ->assertSet('sku', 'ATK-001')
            ->assertSet('quantity', 40)
            ->assertSet('unit', 'rim')
            ->assertSet('minStock', 10);
    }

    public function test_admin_sees_detail_read_only(): void
    {
        $admin = User::factory()->admin()->create();
        $stock = Stock::factory()->create(['name' => 'Barang Member', 'price' => null, 'description' => null]);

        $this->actingAs($admin)
            ->get(route('stocks.show', $stock->id))
            ->assertOk()
            ->assertSee('Barang Member')
            ->assertSee('Tidak ditentukan')
            ->assertDontSee('Sesuaikan Quantity')
            ->assertDontSee('Edit Barang');

        Livewire::test(StockDetail::class, ['stock' => $stock])->call('openEditModal')->assertForbidden();
        Livewire::test(StockDetail::class, ['stock' => $stock])->call('adjustQuantity', 1)->assertForbidden();
    }

    public function test_open_and_close_edit_modal(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Judul Detail', 'description' => null]);
        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('name', 'Diubah sementara')
            ->call('openEditModal')
            ->assertSet('showEditModal', true)
            ->assertSet('name', 'Judul Detail')
            ->assertSet('description', '')
            ->assertSee('Ubah Data Barang')
            ->call('closeEditModal')
            ->assertSet('showEditModal', false);
    }

    public function test_update_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Awal', 'quantity' => 20, 'min_stock' => 5]);
        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('name', 'A')
            ->set('quantity', -2)
            ->set('unit', '')
            ->set('price', -1)
            ->set('minStock', -1)
            ->call('saveStock')
            ->assertHasErrors(['name', 'quantity', 'unit', 'price', 'minStock']);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('name', 'Nama Diperbarui')
            ->set('sku', 'NEW-1')
            ->set('description', '## Spesifikasi')
            ->set('quantity', 4)
            ->set('unit', 'box')
            ->set('price', '')
            ->set('minStock', 5)
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $stock->refresh();
        $this->assertSame('Nama Diperbarui', $stock->name);
        $this->assertSame('NEW-1', $stock->sku);
        $this->assertSame(4, $stock->quantity);
        $this->assertNull($stock->price);
        $this->assertSame('low_stock', $stock->status);
    }

    public function test_adjust_quantity(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 10, 'min_stock' => 5]);
        $this->actingAs($user);

        $component = Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('adjustAmount', 0)
            ->call('adjustQuantity', 1)
            ->assertHasErrors(['adjustAmount'])
            ->set('adjustAmount', 7)
            ->call('adjustQuantity', -1)
            ->assertSet('quantity', 3);

        $this->assertSame('low_stock', $stock->fresh()->status);

        $component->set('adjustAmount', 10)->call('adjustQuantity', -1)->assertSet('quantity', 0);
        $this->assertSame('out_of_stock', $stock->fresh()->status);

        $component->set('adjustAmount', 20)->call('adjustQuantity', 1)->assertSet('quantity', 20);
        $this->assertSame('available', $stock->fresh()->status);
    }

    public function test_cover_photo_upload_and_delete(): void
    {
        Storage::fake('public');
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $component = Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'))
            ->assertHasErrors(['coverPhoto'])
            ->set('coverPhoto', UploadedFile::fake()->image('cover.jpg'))
            ->assertHasNoErrors();

        $stock->refresh();
        $this->assertNotNull($stock->cover_image_path);
        Storage::disk('public')->assertExists($stock->cover_image_path);
        $path = $stock->cover_image_path;

        $component->assertSee('Ganti Cover')
            ->call('confirmDeleteCover')
            ->assertSet('confirmingCoverDeletion', true)
            ->call('cancelDeleteCover')
            ->assertSet('confirmingCoverDeletion', false)
            ->call('confirmDeleteCover')
            ->assertSee('Konfirmasi Hapus Cover')
            ->call('deleteCoverPhoto')
            ->assertSet('confirmingCoverDeletion', false);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($stock->fresh()->cover_image_path);
    }

    public function test_delete_stock_redirects(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('deleteStock')
            ->assertRedirect(route('stocks.index'));

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_confirm_and_cancel_delete_modal_actions(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('confirmDeleteStock')
            ->assertSet('confirmingStockDeletion', true)
            ->assertSee('Konfirmasi Hapus Barang')
            ->call('cancelDeleteStock')
            ->assertSet('confirmingStockDeletion', false);
    }
}
