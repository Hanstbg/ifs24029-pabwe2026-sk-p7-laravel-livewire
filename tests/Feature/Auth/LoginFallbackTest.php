<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login tanpa JavaScript: form login di-submit biasa (POST /login)
 * ketika script Livewire belum termuat.
 */
class LoginFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_form_posts_to_fallback_endpoint_with_csrf(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('method="POST"', false)
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false);
    }

    public function test_member_can_login_without_javascript(): void
    {
        $member = User::factory()->member()->create(['username' => 'member', 'password' => 'member']);

        $this->post('/login', ['username' => 'member', 'password' => 'member'])
            ->assertRedirect(route('stocks.index'));

        $this->assertAuthenticatedAs($member);
    }

    public function test_admin_can_login_without_javascript_using_email_and_remember(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com', 'password' => 'admin']);

        $this->post('/login', ['username' => 'admin@example.com', 'password' => 'admin', 'remember' => '1'])
            ->assertRedirect(route('admin.users'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_invalid_credentials_return_back_with_error(): void
    {
        User::factory()->member()->create(['username' => 'member', 'password' => 'member']);

        $this->from('/login')
            ->post('/login', ['username' => 'member', 'password' => 'salah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('login')
            ->assertSessionHasInput('username', 'member')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_validation_requires_username_and_password(): void
    {
        $this->from('/login')
            ->post('/login', [])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username', 'password']);
    }
}
