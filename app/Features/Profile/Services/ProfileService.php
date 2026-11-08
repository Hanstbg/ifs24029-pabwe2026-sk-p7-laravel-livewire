<?php

namespace App\Features\Profile\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    /** @param array{name: string, username: string, email: string} $data */
    public function updateProfile(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
        ]);

        return $user;
    }

    public function updatePassword(User $user, string $current, string $new): bool
    {
        if (! Hash::check($current, $user->password)) {
            return false;
        }

        $user->update(['password' => $new]);

        return true;
    }

    public function updatePhoto(User $user, UploadedFile $file): User
    {
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->update(['profile_photo_path' => $file->store('profiles', 'public')]);

        return $user;
    }
}
