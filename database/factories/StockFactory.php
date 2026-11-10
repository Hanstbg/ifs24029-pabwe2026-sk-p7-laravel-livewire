<?php

namespace Database\Factories;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Stock> */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(0, 100);
        $minStock = 5;

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'description' => fake()->sentence(),
            'quantity' => $quantity,
            'unit' => 'pcs',
            'price' => fake()->randomFloat(2, 1000, 500000),
            'min_stock' => $minStock,
            'status' => StockStatus::fromQuantity($quantity, $minStock),
            'cover_image_path' => null,
        ];
    }
}