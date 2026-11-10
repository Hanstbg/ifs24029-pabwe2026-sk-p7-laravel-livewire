<?php

namespace App\Features\Ai\Services;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\DTO\GeneratedStockItem;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiStockGeneratorService implements AiClientInterface
{
    public function generate(string $prompt, int $count = 5): array
    {
        $key = config('openai.api_key');

        if (! $key) {
            throw new RuntimeException('OPENAI_API_KEY belum diatur.');
        }

        $response = Http::withToken($key)
            ->timeout((int) config('openai.timeout'))
            ->post(rtrim((string) config('openai.base_url'), '/').'/chat/completions', [
                'model' => config('openai.model'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Kamu asisten inventaris. Balas HANYA JSON: {"items":[{"name":"","sku":"","description":"","quantity":0,"unit":"pcs","price":0,"min_stock":5}]}. Buat tepat '.$count.' item stok barang.',
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gagal menghubungi layanan AI.');
        }

        $decoded = json_decode((string) $response->json('choices.0.message.content'), true);
        $items = is_array($decoded) ? ($decoded['items'] ?? []) : [];

        return array_values(array_map(
            fn (array $item) => GeneratedStockItem::fromArray($item),
            array_filter($items, 'is_array')
        ));
    }
}
