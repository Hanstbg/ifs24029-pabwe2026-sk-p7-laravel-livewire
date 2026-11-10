<?php

namespace Tests\Unit\Services;

use App\Features\Ai\Services\OpenAiStockGeneratorService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiStockGeneratorServiceTest extends TestCase
{
    private function fakeContent(string $url, string $content): void
    {
        Http::fake([
            $url => Http::response(['choices' => [['message' => ['content' => $content]]]], 200),
        ]);
    }

    public function test_empty_prompt_throws_exception(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prompt tidak boleh kosong.');
        $service->generateStocks('   ');
    }

    public function test_successful_generation(): void
    {
        $this->fakeContent('https://api.openai.com/v1/chat/completions', json_encode([
            'stocks' => [
                ['name' => 'Kertas HVS', 'sku' => 'ATK-001', 'description' => 'A4', 'quantity' => 20, 'unit' => 'rim', 'price' => 55000, 'min_stock' => 5],
                ['name' => 'Pulpen', 'sku' => '', 'description' => null, 'quantity' => -3, 'unit' => '', 'price' => 'n/a', 'min_stock' => 'x'],
            ],
        ]));

        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');
        $results = $service->generateStocks('Toko ATK', 2);

        $this->assertCount(2, $results);
        $this->assertSame('Kertas HVS', $results[0]->name);
        $this->assertSame('ATK-001', $results[0]->sku);
        $this->assertSame(20, $results[0]->quantity);
        $this->assertSame('rim', $results[0]->unit);
        $this->assertSame(55000.0, $results[0]->price);
        $this->assertSame(5, $results[0]->minStock);

        $this->assertSame('Pulpen', $results[1]->name);
        $this->assertNull($results[1]->sku);
        $this->assertNull($results[1]->description);
        $this->assertSame(0, $results[1]->quantity);
        $this->assertSame('pcs', $results[1]->unit);
        $this->assertNull($results[1]->price);
        $this->assertSame(5, $results[1]->minStock);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer mock-key')
            && str_contains($request['messages'][0]['content'], 'inventory'));
    }

    public function test_api_failure_throws_exception(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(['error' => ['message' => 'Invalid API Key provided']], 401),
        ]);

        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API Error: Invalid API Key provided');
        $service->generateStocks('Toko');
    }

    public function test_api_failure_without_error_message(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Request failed with status code 500');
        $service->generateStocks('Toko');
    }

    public function test_parse_content_with_markdown_fences(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');
        $results = $service->parseContent("```json\n[{\"name\": \"Code Fence Stock\", \"quantity\": 3}]\n```");

        $this->assertCount(1, $results);
        $this->assertSame('Code Fence Stock', $results[0]->name);
        $this->assertSame(3, $results[0]->quantity);
    }

    public function test_parse_content_invalid_json(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI mengembalikan format respon yang tidak valid.');
        $service->parseContent('Not valid JSON');
    }

    public function test_parse_content_empty_stocks(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak ada item stok yang berhasil di-generate dari AI.');
        $service->parseContent('{"stocks": []}');
    }

    public function test_parse_content_non_list_object(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak ada item stok yang berhasil di-generate dari AI.');
        $service->parseContent('{"other": 1}');
    }

    public function test_parse_content_no_valid_items(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Format item stok yang dikembalikan AI tidak sesuai spesifikasi.');
        $service->parseContent('{"stocks": [{"bad_key": "val"}, "string-item"]}');
    }

    public function test_constructor_handles_swapped_endpoint_and_key(): void
    {
        $this->fakeContent('https://ai.example.com/v1/chat/completions', '{"stocks":[{"name":"Barang"}]}');

        $service = new OpenAiStockGeneratorService('sk-secret-key', 'https://ai.example.com/v1');
        $this->assertCount(1, $service->generateStocks('Toko'));
    }

    public function test_constructor_auto_prefixes_https(): void
    {
        $this->fakeContent('https://api.openai.com/v1/chat/completions', '{"stocks":[{"name":"Barang"}]}');

        $service = new OpenAiStockGeneratorService('api.openai.com/v1', 'mock-key');
        $this->assertCount(1, $service->generateStocks('Toko'));
    }

    public function test_constructor_uses_config_defaults(): void
    {
        config(['openai.endpoint' => 'https://cfg.example.com/v1/', 'openai.key' => 'cfg-key', 'openai.model' => 'cfg-model']);
        $this->fakeContent('https://cfg.example.com/v1/chat/completions', '{"stocks":[{"name":"Barang"}]}');

        $service = new OpenAiStockGeneratorService();
        $this->assertCount(1, $service->generateStocks('Toko'));

        Http::assertSent(fn ($request) => $request['model'] === 'cfg-model');
    }
}
