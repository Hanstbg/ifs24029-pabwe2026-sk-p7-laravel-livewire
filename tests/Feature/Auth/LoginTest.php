<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Livewire\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_required_ids(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('id="login-username"', false)
            ->assertSee('id="login-password"', false)
            ->assertSee('id="login-submit"', false);
    }

    public function test_member_logs_in_by_username(): void
    {
        User::factory()->create(['username' => 'member', 'password' => 'secret']);

        Livewire::test(Login::class)->set('login', 'member')->set('password', 'secret')
            ->call('submit')->assertRedirect('/stocks');
        $this->assertAuthenticated();
    }

    public function test_admin_logs_in_by_email(): void
    {
        User::factory()->admin()->create(['email' => 'a@example.com', 'password' => 'secret']);

        Livewire::test(Login::class)->set('login', 'a@example.com')->set('password', 'secret')
            ->call('submit')->assertRedirect('/admin/users');
    }

    public function test_wrong_password_shows_error(): void
    {
        User::factory()->create(['username' => 'member', 'password' => 'secret']);

        Livewire::test(Login::class)->set('login', 'member')->set('password', 'salah')
            ->call('submit')->assertHasErrors('login');
        $this->assertGuest();
    }

    public function test_validation_required(): void
    {
        Livewire::test(Login::class)->call('submit')->assertHasErrors(['login', 'password']);
    }
}
