<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Livewire\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_renders(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_register_creates_member(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Budi')->set('username', 'budi')->set('email', 'budi@example.com')
            ->set('password', 'secret')->set('password_confirmation', 'secret')
            ->call('submit')->assertRedirect('/stocks');

        $this->assertDatabaseHas('users', ['username' => 'budi', 'role' => 'member']);
        $this->assertAuthenticated();
    }

    public function test_register_validation_errors(): void
    {
        User::factory()->create(['username' => 'budi']);

        Livewire::test(Register::class)
            ->set('username', 'budi')->set('password', 'a')->set('password_confirmation', 'b')
            ->call('submit')->assertHasErrors(['name', 'username', 'email', 'password']);
    }
}
