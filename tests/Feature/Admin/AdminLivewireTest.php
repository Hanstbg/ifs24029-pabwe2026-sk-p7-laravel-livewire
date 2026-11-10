<?php

namespace Tests\Feature\Admin;

use App\Features\Admin\Livewire\AdminUserStockList;
use App\Features\Admin\Livewire\UserList;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AdminLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_forbidden_from_admin_pages(): void
    {
        $member = User::factory()->member()->create();
        $targetUser = User::factory()->member()->create();

        $this->actingAs($member)
            ->get('/admin/users')
            ->assertStatus(403);

        $this->actingAs($member)
            ->get("/admin/users/{$targetUser->id}/stocks")
            ->assertStatus(403);
    }

    public function test_admin_can_view_user_list(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@test.com']);
        User::factory()->create(['name' => 'Citra Lestari', 'email' => 'citra@test.com']);

        $this->actingAs($admin);

        Livewire::test(UserList::class)
            ->assertSee('Budi Santoso')
            ->assertSee('Citra Lestari')
            ->set('search', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Citra Lestari');
    }

    public function test_admin_can_view_user_stocks_read_only(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create(['name' => 'Dewi Sartika']);

        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Kertas Folio', 'sku' => 'ATK-010', 'price' => 45000, 'status' => 'available']);
        Stock::factory()->outOfStock()->create(['user_id' => $member->id, 'name' => 'Tinta Stempel', 'price' => null]);

        $this->actingAs($admin);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->assertSee('Dewi Sartika')
            ->assertSee('Kertas Folio')
            ->assertSee('Tinta Stempel')
            ->assertSee('Rp 45.000')
            ->set('search', 'Folio')
            ->assertSee('Kertas Folio')
            ->assertDontSee('Tinta Stempel')
            ->set('search', '')
            ->set('statusFilter', 'out_of_stock')
            ->assertSee('Tinta Stempel')
            ->assertDontSee('Kertas Folio');
    }

    public function test_admin_stock_list_has_no_mutating_actions(): void
    {
        $this->assertFalse(method_exists(AdminUserStockList::class, 'deleteStock'));
        $this->assertFalse(method_exists(AdminUserStockList::class, 'adjustQuantity'));
        $this->assertFalse(method_exists(AdminUserStockList::class, 'saveStock'));
    }

    public function test_admin_can_export_user_stocks(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create(['name' => 'Eko Prasetyo']);
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Stok Export Admin']);

        $this->actingAs($admin);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->call('exportExcel')
            ->assertFileDownloaded();
    }
}
