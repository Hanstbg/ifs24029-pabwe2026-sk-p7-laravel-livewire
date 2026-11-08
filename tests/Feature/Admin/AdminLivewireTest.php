<?php

namespace Tests\Feature\Admin;

use App\Features\Admin\Livewire\AdminUserStockList;
use App\Features\Admin\Livewire\UserList;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_user_list_with_search_and_counts(): void
    {
        $member = User::factory()->create(['name' => 'Siti Member']);
        User::factory()->create(['name' => 'Budi Lain']);
        Stock::factory()->count(3)->create(['user_id' => $member->id]);

        Livewire::test(UserList::class)->assertSee('Siti Member')->assertSee('Budi Lain')
            ->set('search', 'Siti')->assertSee('Siti Member')->assertDontSee('Budi Lain');
    }

    public function test_user_stock_list_readonly(): void
    {
        $member = User::factory()->create();
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Barang A', 'status' => 'available', 'quantity' => 50]);
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Barang B', 'status' => 'out_of_stock', 'quantity' => 0]);
        Stock::factory()->create(['name' => 'Bukan Milik']);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->assertSee('Barang A')->assertDontSee('Bukan Milik')
            ->set('search', 'Barang A')->assertDontSee('Barang B')
            ->set('search', '')->set('statusFilter', 'out_of_stock')->assertSee('Barang B')->assertDontSee('Barang A');
    }

    public function test_admin_routes_render(): void
    {
        $member = User::factory()->create();

        $this->get('/admin/users')->assertOk();
        $this->get("/admin/users/{$member->id}/stocks")->assertOk();
    }
}
