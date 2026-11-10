<?php

namespace Tests\Feature;

use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects(): void
    {
        $this->get('/')->assertOk()->assertSee('id="login-submit"', false);
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/stocks');
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertRedirect('/admin/users');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/stocks')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect('/stocks');
        $this->actingAs(User::factory()->admin()->create())->get('/login')->assertRedirect('/admin/users');
    }

    public function test_role_restrictions(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/stocks')->assertForbidden();
    }

    public function test_member_pages_render(): void
    {
        $member = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $member->id]);

        $this->actingAs($member)->get('/stocks')->assertOk();
        $this->get("/stocks/{$stock->id}")->assertOk();
        $this->get('/profile')->assertOk();
    }

    public function test_ownership_enforced(): void
    {
        $member = User::factory()->create();
        $other = Stock::factory()->create();

        $this->actingAs($member)->get("/stocks/{$other->id}")->assertForbidden();
        $this->get('/stocks/99999')->assertNotFound();
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
