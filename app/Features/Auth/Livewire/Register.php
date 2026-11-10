<?php

namespace App\Features\Auth\Livewire;

use App\Features\Auth\Actions\RegisterUserAction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Daftar')]
class Register extends Component
{
    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function submit(RegisterUserAction $action)
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
        ]);

        $action->execute($data);
        session()->regenerate();

        return $this->redirect('/stocks');
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
