<?php

namespace Tests\Unit;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockExcelService;
use Tests\TestCase;

class StockExcelServiceTest extends TestCase
{
    public function test_export_and_parse_round_trip(): void
    {
        $service = new StockExcelService;
        $stocks = collect([
            Stock::factory()->make(['name' => 'A', 'sku' => 'S1', 'quantity' => 7, 'unit' => 'box', 'price' => 1500, 'min_stock' => 2, 'status' => 'available']),
            Stock::factory()->make(['name' => 'B', 'sku' => null, 'quantity' => 0, 'unit' => 'pcs', 'price' => null, 'min_stock' => 5, 'status' => 'out_of_stock']),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'xl');
        file_put_contents($path, $service->export($stocks));
        $rows = $service->parse($path);
        unlink($path);

        $this->assertCount(2, $rows);
        $this->assertSame('A', $rows[0]['name']);
        $this->assertSame(7, $rows[0]['quantity']);
        $this->assertSame('box', $rows[0]['unit']);
        $this->assertNull($rows[1]['sku']);
        $this->assertNull($rows[1]['price']);
    }

    public function test_parse_skips_blank_names_and_applies_defaults(): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(StockExcelService::HEADERS, null, 'A1');
        $sheet->fromArray(['', 'x', 1], null, 'A2');
        $sheet->fromArray(['Hanya Nama'], null, 'A3');

        $path = tempnam(sys_get_temp_dir(), 'xl');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $rows = (new StockExcelService)->parse($path);
        unlink($path);

        $this->assertCount(1, $rows);
        $this->assertSame('pcs', $rows[0]['unit']);
        $this->assertSame(5, $rows[0]['min_stock']);
        $this->assertSame(0, $rows[0]['quantity']);
    }
}
