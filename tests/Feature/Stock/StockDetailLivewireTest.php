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

        $this->get(route('stocks.show', $stock->id))
            ->assertRedirect(route('login'));
    }

    public function test_member_cannot_access_other_member_stock(): void
    {
        $owner = User::factory()->member()->create();
        $otherMember = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherMember);

        $this->get(route('stocks.show', $stock->id))->assertStatus(403);
    }

    public function test_member_can_view_own_stock_detail(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Kertas HVS Utama',
            'sku' => 'ATK-100',
            'description' => '# Deskripsi Markdown',
            'quantity' => 12,
            'unit' => 'rim',
            'price' => 52000,
            'min_stock' => 4,
        ]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertOk()
            ->assertSet('name', 'Kertas HVS Utama')
            ->assertSet('sku', 'ATK-100')
            ->assertSet('description', '# Deskripsi Markdown')
            ->assertSet('quantity', 12)
            ->assertSet('unit', 'rim')
            ->assertSet('price', 52000)
            ->assertSet('minStock', 4)
            ->assertSee('Kertas HVS Utama');
    }

    public function test_detail_without_price_or_description(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'price' => null, 'description' => null]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertSet('price', null)
            ->assertSet('description', '')
            ->assertSee('Belum ada deskripsi');
    }

    public function test_admin_can_view_but_not_modify_other_users_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $member->id, 'quantity' => 8]);

        $this->actingAs($admin);

        Livewire::test(StockDetail::class, ['stock' => $stock])->assertOk();

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('adjustQuantity', 1)
            ->assertForbidden();

        $this->assertEquals(8, $stock->fresh()->quantity);
    }

    public function test_open_and_close_edit_modal(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nama Detail',
            'description' => 'Deskripsi lama',
        ]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertSet('showEditModal', false)
            ->call('openEditModal')
            ->assertSet('showEditModal', true)
            ->assertSet('name', 'Nama Detail')
            ->assertSet('description', 'Deskripsi lama')
            ->call('closeEditModal')
            ->assertSet('showEditModal', false);
    }

    public function test_update_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Nama Awal']);

        $this->actingAs($user);

        // Validation errors
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('name', 'ab') // min:3
            ->set('quantity', -5)
            ->set('minStock', 'x')
            ->set('unit', '')
            ->call('saveStock')
            ->assertHasErrors(['name', 'quantity', 'minStock', 'unit']);

        // Success
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('name', 'Nama Telah Diperbarui')
            ->set('sku', 'NEW-SKU')
            ->set('description', '## Rincian Baru')
            ->set('quantity', 2)
            ->set('unit', 'box')
            ->set('price', 9900)
            ->set('minStock', 5)
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'name' => 'Nama Telah Diperbarui',
            'sku' => 'NEW-SKU',
            'description' => '## Rincian Baru',
            'quantity' => 2,
            'unit' => 'box',
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);
    }

    public function test_adjust_quantity_syncs_status(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'quantity' => 6,
            'min_stock' => 5,
            'status' => 'available',
        ]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('adjustQuantity', -1)
            ->assertSet('quantity', 5);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 5, 'status' => 'low_stock']);

        Livewire::test(StockDetail::class, ['stock' => $stock->fresh()])
            ->call('adjustQuantity', -10)
            ->assertSet('quantity', 0);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 0, 'status' => 'out_of_stock']);
    }

    public function test_cover_photo_upload_and_delete(): void
    {
        Storage::fake('public');

        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'cover_image_path' => null]);

        $this->actingAs($user);

        // Invalid file
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100);
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', $invalidFile)
            ->assertHasErrors(['coverPhoto']);

        // Valid image upload
        $imageFile = UploadedFile::fake()->image('cover.jpg', 800, 400);
        $component = Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', $imageFile)
            ->assertHasNoErrors();

        $stock->refresh();
        $this->assertNotNull($stock->cover_image_path);
        Storage::disk('public')->assertExists($stock->cover_image_path);

        // Delete cover photo
        $component->call('deleteCoverPhoto');

        $stock->refresh();
        $this->assertNull($stock->cover_image_path);
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
            ->assertSet('confirmingStockDeletion', false)
            ->call('confirmDeleteStock')
            ->assertSet('confirmingStockDeletion', true)
            ->call('cancelDeleteStock')
            ->assertSet('confirmingStockDeletion', false)
            ->assertSet('confirmingCoverDeletion', false)
            ->call('confirmDeleteCover')
            ->assertSet('confirmingCoverDeletion', true)
            ->call('cancelDeleteCover')
            ->assertSet('confirmingCoverDeletion', false);
    }
}
