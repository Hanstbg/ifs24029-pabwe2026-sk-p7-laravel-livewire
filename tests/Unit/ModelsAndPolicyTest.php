<?php

namespace Tests\Unit;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Policies\StockPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsAndPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model(): void
    {
        $user = User::factory()->create();
        Stock::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($user->isAdmin());
        $this->assertTrue(User::factory()->admin()->make()->isAdmin());
        $this->assertCount(1, $user->stocks);
    }

    public function test_stock_model(): void
    {
        $stock = Stock::factory()->create(['quantity' => 0, 'min_stock' => 5]);
        $stock->syncStatus();

        $this->assertSame(StockStatus::OutOfStock, $stock->status);
        $this->assertTrue($stock->user->is(User::find($stock->user_id)));
    }

    public function test_policy(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id]);
        $policy = new StockPolicy;

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue($policy->$ability($owner, $stock));
            $this->assertFalse($policy->$ability($other, $stock));
        }
    }
}
