<?php

namespace Tests\Unit;

use App\Features\Admin\Services\AdminUserService;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_with_counts_and_search(): void
    {
        $a = User::factory()->create(['name' => 'Ani', 'username' => 'ani']);
        User::factory()->create(['name' => 'Budi', 'username' => 'budi']);
        Stock::factory()->count(2)->create(['user_id' => $a->id]);
        $service = app(AdminUserService::class);

        $this->assertSame(2, $service->users()->total());
        $found = $service->users('Ani');
        $this->assertSame(1, $found->total());
        $this->assertSame(2, $found->first()->stocks_count);
    }

    public function test_stocks_of_user(): void
    {
        $user = User::factory()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Q', 'status' => 'available']);
        Stock::factory()->create();

        $this->assertSame(1, app(AdminUserService::class)->stocksOf($user, 'Q', 'available')->total());
    }
}
