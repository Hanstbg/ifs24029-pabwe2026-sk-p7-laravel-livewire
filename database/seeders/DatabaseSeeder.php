<?php

namespace Database\Seeders;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'password' => Hash::make('admin'),
                'role' => 'admin',
            ]
        );

        $member = User::firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'name' => 'Member User',
                'username' => 'member',
                'password' => Hash::make('member'),
                'role' => 'member',
            ]
        );

        $samples = [
            ['name' => 'Kertas HVS A4 80gr', 'sku' => 'ATK-001', 'quantity' => 40, 'unit' => 'rim', 'price' => 55000, 'min_stock' => 10,
                'description' => "Kertas HVS ukuran **A4** 80 gram.\n\n- Isi 500 lembar per rim\n- Untuk printer laser & inkjet"],
            ['name' => 'Tinta Printer Hitam', 'sku' => 'ATK-002', 'quantity' => 3, 'unit' => 'botol', 'price' => 85000, 'min_stock' => 5,
                'description' => 'Tinta refill hitam 100 ml untuk printer tipe *ink tank*.'],
            ['name' => 'Mouse Wireless', 'sku' => 'ELK-001', 'quantity' => 0, 'unit' => 'pcs', 'price' => 120000, 'min_stock' => 2,
                'description' => 'Mouse wireless 2.4 GHz dengan receiver USB.'],
        ];

        foreach ($samples as $sample) {
            Stock::firstOrCreate(
                ['user_id' => $member->id, 'name' => $sample['name']],
                $sample + ['status' => StockStatus::fromQuantity($sample['quantity'], $sample['min_stock'])->value]
            );
        }
    }
}
