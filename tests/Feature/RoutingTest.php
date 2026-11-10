<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_shows_login_page_for_guest(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Masuk ke Akun Anda')
            ->assertSee('id="login-username"', false)
            ->assertSee('id="login-password"', false)
            ->assertSee('id="login-submit"', false);
    }

    public function test_logged_in_user_visiting_guest_pages_is_redirected_by_role(): void
    {
        $member = User::factory()->member()->create();
        $this->actingAs($member)->get('/login')->assertRedirect(route('stocks.index'));
        $this->actingAs($member)->get('/register')->assertRedirect(route('stocks.index'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/login')->assertRedirect(route('admin.users'));
    }

    public function test_root_redirects_member_to_stocks(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member)
            ->get('/')
            ->assertRedirect(route('stocks.index'));
    }

    public function test_root_redirects_admin_to_admin_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect(route('admin.users'));
    }

    public function test_health_check_endpoint(): void
    {
        $this->get('/up')
            ->assertStatus(200);
    }
}

