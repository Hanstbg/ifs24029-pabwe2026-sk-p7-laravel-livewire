<?php

namespace Tests\Unit;

use App\Features\Profile\Services\ProfileService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_profile(): void
    {
        $user = User::factory()->create();

        (new ProfileService)->updateProfile($user, ['name' => 'N', 'username' => 'nn', 'email' => 'n@example.com']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'N', 'username' => 'nn']);
    }

    public function test_update_password(): void
    {
        $user = User::factory()->create(['password' => 'lama']);
        $service = new ProfileService;

        $this->assertFalse($service->updatePassword($user, 'salah', 'baru'));
        $this->assertTrue($service->updatePassword($user, 'lama', 'baru'));
        $this->assertTrue(Hash::check('baru', $user->fresh()->password));
    }

    public function test_update_photo_replaces_old(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $service = new ProfileService;

        $service->updatePhoto($user, UploadedFile::fake()->image('a.jpg'));
        $first = $user->profile_photo_path;
        $service->updatePhoto($user, UploadedFile::fake()->image('b.jpg'));

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($user->profile_photo_path);
    }
}
