<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Features\Stock\Services\StockExcelService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class StockExcelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockExcelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockExcelService(new StockRepository());
    }

    public function test_build_spreadsheet_and_export_response(): void
    {
        $user = User::factory()->create();
        $stocks = Stock::factory()->count(2)->create(['user_id' => $user->id]);
        $stocks->push(Stock::factory()->outOfStock()->create(['user_id' => $user->id, 'sku' => null, 'description' => null, 'price' => null]));

        $spreadsheet = $this->service->buildSpreadsheet($stocks, 'Custom Stocks');
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
        $this->assertEquals('Custom Stocks', $spreadsheet->getActiveSheet()->getTitle());

        $response = $this->service->exportToResponse($stocks, 'test.xlsx');
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $outputContent = ob_get_clean();
        $this->assertNotEmpty($outputContent);
    }

    public function test_import_spreadsheet(): void
    {
        $user = User::factory()->create();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach (['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock'] as $i => $header) {
            $sheet->setCellValue(chr(65 + $i).'1', $header);
        }

        // Row 2: complete row, low stock (quantity <= min_stock)
        $sheet->fromArray([1, 'Imported Stock 1', 'IMP-001', 'Desc 1', 3, 'box', 12500, 5], null, 'A2');

        // Row 3: placeholders '-' / blanks, non numeric price & min stock, negative quantity -> out of stock
        $sheet->fromArray([2, 'Imported Stock 2', '-', '-', -4, '', 'abc', 'n/a'], null, 'A3');

        // Row 4: plenty of stock -> available
        $sheet->fromArray([3, 'Imported Stock 3', 'IMP-003', 'Banyak', 50, 'rim', '7500.50', 10], null, 'A4');

        // Row 5: empty name is skipped
        $sheet->fromArray([4, '', 'IMP-004', 'Skip this', 9, 'pcs', 1, 1], null, 'A5');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_excel_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $result = $this->service->import($tempPath, $user->id);
        @unlink($tempPath);

        $this->assertEquals(3, $result['imported']);
        $this->assertSame([], $result['errors']);
        $this->assertDatabaseCount('stocks', 3);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Imported Stock 1',
            'sku' => 'IMP-001',
            'description' => 'Desc 1',
            'quantity' => 3,
            'unit' => 'box',
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Imported Stock 2',
            'sku' => null,
            'description' => null,
            'quantity' => 0,
            'unit' => 'pcs',
            'price' => null,
            'min_stock' => 5,
            'status' => 'out_of_stock',
        ]);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Imported Stock 3',
            'quantity' => 50,
            'status' => 'available',
        ]);
    }

    public function test_import_empty_sheet_inserts_nothing(): void
    {
        $user = User::factory()->create();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Name');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_excel_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $result = $this->service->import($tempPath, $user->id);
        @unlink($tempPath);

        $this->assertEquals(0, $result['imported']);
        $this->assertDatabaseCount('stocks', 0);
    }
}
