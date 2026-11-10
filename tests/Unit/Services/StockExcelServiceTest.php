<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Features\Stock\Services\StockExcelService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Barang A', 'sku' => 'A-1', 'quantity' => 7, 'price' => 1200]);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Barang B', 'sku' => null, 'description' => null, 'price' => null]);
        $stocks = Stock::query()->orderBy('id')->get();

        $spreadsheet = $this->service->buildSpreadsheet($stocks, 'Custom Stocks');
        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('Custom Stocks', $sheet->getTitle());
        $this->assertSame('Name', $sheet->getCell('B1')->getValue());
        $this->assertSame('Min Stock', $sheet->getCell('H1')->getValue());
        $this->assertSame('Barang A', $sheet->getCell('B2')->getValue());
        $this->assertSame(7, $sheet->getCell('E2')->getValue());
        $this->assertSame('-', $sheet->getCell('C3')->getValue());
        $this->assertSame('-', $sheet->getCell('G3')->getValue());

        $response = $this->service->exportToResponse($stocks, 'test.xlsx');
        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $this->assertNotEmpty(ob_get_clean());
    }

    public function test_build_spreadsheet_handles_missing_created_at(): void
    {
        $stock = Stock::factory()->make(['created_at' => null]);
        $sheet = $this->service->buildSpreadsheet(new \Illuminate\Database\Eloquent\Collection([$stock]))->getActiveSheet();

        $this->assertSame('-', $sheet->getCell('J2')->getValue());
    }

    public function test_export_then_import_roundtrip(): void
    {
        $owner = User::factory()->create();
        Stock::factory()->create(['user_id' => $owner->id, 'name' => 'Roundtrip', 'sku' => 'RT-1', 'quantity' => 2, 'min_stock' => 5, 'price' => 999]);

        $path = tempnam(sys_get_temp_dir(), 'stk').'.xlsx';
        (new Xlsx($this->service->buildSpreadsheet(Stock::all())))->save($path);

        $target = User::factory()->create();
        $result = $this->service->import($path, $target->id);
        @unlink($path);

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $target->id, 'name' => 'Roundtrip', 'sku' => 'RT-1', 'quantity' => 2, 'status' => 'low_stock',
        ]);
    }

    public function test_import_spreadsheet_validation_rules(): void
    {
        $user = User::factory()->create();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock', 'Status'],
            [1, 'Valid Item', 'V-1', 'Desc', 10, 'box', 5000, 2, 'ignored'],
            [2, 'Empty Qty', '-', '-', null, null, '-', null, null],
            [3, '', 'SKIP', null, 5, null, null, null, null],
            [4, 'Bad Qty', null, null, 'abc', null, null, null, null],
            [5, 'Negative Qty', null, null, -4, null, null, null, null],
            [6, 'Name', null, null, 1, null, null, null, null],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'stk').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $result = $this->service->import($path, $user->id);
        @unlink($path);

        $this->assertSame(2, $result['imported']);
        $this->assertCount(2, $result['errors']);
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'Valid Item', 'unit' => 'box', 'status' => 'available', 'min_stock' => 2]);
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'Empty Qty', 'sku' => null, 'quantity' => 0, 'unit' => 'pcs', 'price' => null, 'min_stock' => 5, 'status' => 'out_of_stock']);
        $this->assertDatabaseMissing('stocks', ['name' => 'Bad Qty']);
        $this->assertDatabaseMissing('stocks', ['name' => 'Negative Qty']);
    }

    public function test_import_header_only_file(): void
    {
        $user = User::factory()->create();
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([['No', 'Name']]);

        $path = tempnam(sys_get_temp_dir(), 'stk').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $result = $this->service->import($path, $user->id);
        @unlink($path);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, Stock::count());
        $this->assertNotNull(IOFactory::class);
    }
}
