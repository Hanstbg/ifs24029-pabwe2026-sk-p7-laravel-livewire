<?php

namespace Tests\Unit;

use App\Features\Stock\Models\Stock;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStockOwnership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function request(?User $user, mixed $stock = null): Request
    {
        $request = Request::create('/stocks/1');
        $request->setUserResolver(fn () => $user);

        $route = new Route('GET', '/stocks/{stock}', []);
        $route->bind($request);
        $route->setParameter('stock', $stock);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    public function test_role_allows_matching_role(): void
    {
        $response = (new EnsureRole)->handle($this->request(User::factory()->admin()->create()), fn () => response('ok'), 'admin');

        $this->assertSame('ok', $response->getContent());
    }

    public function test_role_rejects_wrong_role_and_guest(): void
    {
        $this->expectException(HttpException::class);
        (new EnsureRole)->handle($this->request(User::factory()->create()), fn () => response('ok'), 'admin');
    }

    public function test_role_rejects_guest(): void
    {
        $this->expectException(HttpException::class);
        (new EnsureRole)->handle($this->request(null), fn () => response('ok'), 'admin');
    }

    public function test_ownership_allows_owner_with_model_or_id(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $a = (new EnsureStockOwnership)->handle($this->request($user, $stock), fn () => response('ok'));
        $b = (new EnsureStockOwnership)->handle($this->request($user, (string) $stock->id), fn () => response('ok'));

        $this->assertSame('ok', $a->getContent());
        $this->assertSame('ok', $b->getContent());
    }

    public function test_ownership_rejects_other_user(): void
    {
        $this->expectException(HttpException::class);
        (new EnsureStockOwnership)->handle($this->request(User::factory()->create(), Stock::factory()->create()), fn () => response('ok'));
    }

    public function test_ownership_missing_stock_is_404(): void
    {
        try {
            (new EnsureStockOwnership)->handle($this->request(User::factory()->create(), '9999'), fn () => response('ok'));
            $this->fail('Expected 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }
}
