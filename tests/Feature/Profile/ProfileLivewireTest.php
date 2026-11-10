<?php

namespace Tests\Feature\Profile;

use App\Features\Profile\Livewire\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileLivewireTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['password' => 'lama']);
        $this->actingAs($this->user);
    }

    public function test_mount_fills_data(): void
    {
        Livewire::test(Profile::class)->assertSet('username', $this->user->username);
    }

    public function test_update_profile(): void
    {
        Livewire::test(Profile::class)->set('name', 'Nama Baru')->call('saveProfile');

        $this->assertSame('Nama Baru', $this->user->fresh()->name);
    }

    public function test_update_profile_validation_unique(): void
    {
        $other = User::factory()->create();

        Livewire::test(Profile::class)->set('username', $other->username)->call('saveProfile')->assertHasErrors('username');
    }

    public function test_change_password(): void
    {
        Livewire::test(Profile::class)->set('current_password', 'lama')->set('new_password', 'baru')
            ->set('new_password_confirmation', 'baru')->call('savePassword')->assertHasNoErrors();

        $this->assertTrue(Hash::check('baru', $this->user->fresh()->password));
    }

    public function test_change_password_wrong_current(): void
    {
        Livewire::test(Profile::class)->set('current_password', 'salah')->set('new_password', 'baru')
            ->set('new_password_confirmation', 'baru')->call('savePassword')->assertHasErrors('current_password');
    }

    public function test_upload_photo(): void
    {
        Storage::fake('public');

        Livewire::test(Profile::class)->set('photo', UploadedFile::fake()->image('p.jpg'))->call('savePhoto')
            ->set('photo', UploadedFile::fake()->image('q.jpg'))->call('savePhoto');

        Storage::disk('public')->assertExists($this->user->fresh()->profile_photo_path);
    }

    public function test_photo_validation(): void
    {
        Livewire::test(Profile::class)->call('savePhoto')->assertHasErrors('photo');
    }
}
