<?php

namespace Tests\Unit;

use App\Features\Ai\DTO\GeneratedStockItem;
use App\Features\Ai\Services\OpenAiStockGeneratorService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiStockGeneratorServiceTest extends TestCase
{
    public function test_requires_api_key(): void
    {
        config(['openai.api_key' => null]);

        $this->expectException(RuntimeException::class);
        (new OpenAiStockGeneratorService)->generate('x');
    }

    public function test_failed_response_throws(): void
    {
        config(['openai.api_key' => 'k']);
        Http::fake(['*' => Http::response([], 500)]);

        $this->expectException(RuntimeException::class);
        (new OpenAiStockGeneratorService)->generate('x');
    }

    public function test_parses_items(): void
    {
        config(['openai.api_key' => 'k']);
        $content = json_encode(['items' => [['name' => 'Pena', 'quantity' => 4], 'bukan-array', ['name' => 'Buku']]]);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => $content]]]])]);

        $items = (new OpenAiStockGeneratorService)->generate('alat tulis', 2);

        $this->assertCount(2, $items);
        $this->assertContainsOnlyInstancesOf(GeneratedStockItem::class, $items);
        $this->assertSame('Pena', $items[0]->name);
    }

    public function test_invalid_json_returns_empty(): void
    {
        config(['openai.api_key' => 'k']);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'bukan json']]]])]);

        $this->assertSame([], (new OpenAiStockGeneratorService)->generate('x'));
    }
}
