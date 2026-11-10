<?php

namespace Tests\Unit;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\Services\OpenAiStockGeneratorService;
use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Repositories\StockRepository;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_bindings(): void
    {
        $this->assertInstanceOf(StockRepository::class, app(StockRepositoryInterface::class));
        $this->assertInstanceOf(OpenAiStockGeneratorService::class, app(AiClientInterface::class));
    }

    public function test_rate_limiter(): void
    {
        $limiter = RateLimiter::limiter('1000-per-second');

        $this->assertInstanceOf(Limit::class, $limiter(Request::create('/')));

        $request = Request::create('/');
        $request->setUserResolver(fn () => new User(['id' => 1]));
        $this->assertInstanceOf(Limit::class, $limiter($request));
    }

    public function test_force_https(): void
    {
        config(['app.force_https' => true]);
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', url('/x'));

        URL::forceScheme('http');
    }
}
