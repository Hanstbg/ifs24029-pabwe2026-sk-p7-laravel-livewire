<?php

namespace Database\Seeders;

use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@example.com',
            'password' => Hash::make('admin'), 'role' => 'admin',
        ]);

        $member = User::create([
            'name' => 'Member', 'username' => 'member', 'email' => 'member@example.com',
            'password' => Hash::make('member'), 'role' => 'member',
        ]);

        Stock::factory()->count(5)->create(['user_id' => $member->id]);
    }
}
