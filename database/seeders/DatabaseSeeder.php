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

        $items = [
            ['name' => 'Kertas HVS A4 80gsm', 'sku' => 'ATK-001', 'description' => "Kertas **HVS A4** untuk kebutuhan cetak kantor.\n\n- Isi 500 lembar per rim", 'quantity' => 120, 'unit' => 'rim', 'price' => 52000, 'min_stock' => 20],
            ['name' => 'Pulpen Gel Hitam', 'sku' => 'ATK-002', 'description' => 'Pulpen gel tinta hitam 0.5mm.', 'quantity' => 4, 'unit' => 'pcs', 'price' => 4500, 'min_stock' => 10],
            ['name' => 'Tinta Printer Hitam', 'sku' => 'ELK-010', 'description' => 'Tinta refill printer, stok sedang kosong.', 'quantity' => 0, 'unit' => 'botol', 'price' => 85000, 'min_stock' => 3],
        ];

        foreach ($items as $item) {
            Stock::firstOrCreate(
                ['user_id' => $member->id, 'sku' => $item['sku']],
                $item + ['status' => StockStatus::fromQuantity($item['quantity'], $item['min_stock'])->value]
            );
        }
    }
}
