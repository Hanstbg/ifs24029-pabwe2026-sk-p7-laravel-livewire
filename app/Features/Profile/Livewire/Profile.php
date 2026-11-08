<?php

namespace App\Features\Profile\Livewire;

use App\Features\Profile\Services\ProfileService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Profil')]
class Profile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public $photo = null;

    public ?string $message = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
    }

    public function saveProfile(ProfileService $service): void
    {
        $user = auth()->user();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $service->updateProfile($user, $data);
        $this->message = 'Profil berhasil diperbarui.';
    }

    public function savePassword(ProfileService $service): void
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:4', 'confirmed'],
        ]);

        if (! $service->updatePassword(auth()->user(), $this->current_password, $this->new_password)) {
            $this->addError('current_password', 'Password saat ini salah.');

            return;
        }

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->message = 'Password berhasil diubah.';
    }

    public function savePhoto(ProfileService $service): void
    {
        $this->validate(['photo' => ['required', 'image', 'max:2048']]);

        $service->updatePhoto(auth()->user(), $this->photo);
        $this->photo = null;
        $this->message = 'Foto berhasil diperbarui.';
    }

    public function render()
    {
        return view('livewire.profile.profile');
    }
}
