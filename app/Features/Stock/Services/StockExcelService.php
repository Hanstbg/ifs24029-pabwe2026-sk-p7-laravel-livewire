<?php

namespace App\Features\Stock\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockExcelService
{
    /**
     * Column headers used for export (and expected for import).
     *
     * @var array<int, string>
     */
    public const HEADERS = ['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock', 'Status', 'Created At'];

    public function __construct(
        protected StockRepositoryInterface $stockRepository
    ) {}

    /**
     * Build a Spreadsheet instance containing stock data.
     *
     * @param Collection<int, Stock> $stocks
     */
    public function buildSpreadsheet(Collection $stocks, string $sheetTitle = 'Stocks'): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetTitle, 0, 31));

        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

        foreach (self::HEADERS as $index => $header) {
            $sheet->setCellValue("{$columns[$index]}1", $header);
        }

        $headerRange = 'A1:J1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowNumber = 2;
        foreach ($stocks as $idx => $stock) {
            $sheet->setCellValue("A{$rowNumber}", $idx + 1);
            $sheet->setCellValue("B{$rowNumber}", $stock->name);
            $sheet->setCellValue("C{$rowNumber}", $stock->sku ?? '-');
            $sheet->setCellValue("D{$rowNumber}", $stock->description ?? '-');
            $sheet->setCellValue("E{$rowNumber}", (int) $stock->quantity);
            $sheet->setCellValue("F{$rowNumber}", $stock->unit);
            $sheet->setCellValue("G{$rowNumber}", $stock->price !== null ? (float) $stock->price : '-');
            $sheet->setCellValue("H{$rowNumber}", (int) $stock->min_stock);
            $sheet->setCellValue("I{$rowNumber}", $stock->status);
            $sheet->setCellValue("J{$rowNumber}", $stock->created_at ? $stock->created_at->format('Y-m-d H:i') : '-');
            $rowNumber++;
        }

        $lastRow = max(2, $rowNumber - 1);
        $sheet->getStyle("A1:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach ($columns as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Export stocks as a downloadable streamed response.
     *
     * @param Collection<int, Stock> $stocks
     */
    public function exportToResponse(Collection $stocks, string $filename = 'stocks.xlsx'): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($stocks);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Import stocks from a spreadsheet file path.
     * Expected columns: No | Name | SKU | Description | Quantity | Unit | Price | Min Stock | (Status ignored, re-derived).
     *
     * @return array{imported: int, errors: array<int, string>}
     */
    public function import(string $filePath, int $userId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, true);

        $imported = 0;
        $errors = [];
        $records = [];
        $now = Carbon::now();

        foreach (array_slice($rows, 1, null, true) as $rowIndex => $row) {
            $name = trim((string) ($row['B'] ?? ''));

            if ($name === '' || strtolower($name) === 'name') {
                continue;
            }

            $rawQuantity = $this->clean($row['E'] ?? null);
            $quantity = $rawQuantity === null ? 0 : $this->toInt($rawQuantity);
            if ($quantity === null || $quantity < 0) {
                $errors[] = "Baris {$rowIndex}: quantity tidak valid, baris dilewati.";
                continue;
            }

            $minStock = $this->toInt($row['H'] ?? null) ?? 5;
            $minStock = max(0, $minStock);
            $price = $this->toFloat($row['G'] ?? null);
            $unit = $this->clean($row['F'] ?? null) ?? 'pcs';

            $records[] = [
                'user_id' => $userId,
                'name' => mb_substr($name, 0, 255),
                'sku' => $this->clean($row['C'] ?? null),
                'description' => $this->clean($row['D'] ?? null),
                'quantity' => $quantity,
                'unit' => mb_substr($unit, 0, 30),
                'price' => $price,
                'min_stock' => $minStock,
                'status' => StockStatus::fromQuantity($quantity, $minStock)->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $imported++;
        }

        $this->stockRepository->insertMany($records);

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return ($value === '' || $value === '-') ? null : $value;
    }

    private function toInt(mixed $value): ?int
    {
        $value = $this->clean($value);

        return ($value !== null && is_numeric($value)) ? (int) $value : null;
    }

    private function toFloat(mixed $value): ?float
    {
        $value = $this->clean($value);

        return ($value !== null && is_numeric($value)) ? (float) $value : null;
    }
}
