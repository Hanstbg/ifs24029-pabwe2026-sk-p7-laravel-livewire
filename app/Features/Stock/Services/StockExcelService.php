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
    public function __construct(
        protected StockRepositoryInterface $stockRepository
    ) {}

    /**
     * Build a Spreadsheet instance containing stocks data.
     *
     * @param Collection<int, Stock> $stocks
     */
    public function buildSpreadsheet(Collection $stocks, string $sheetTitle = 'Stocks'): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetTitle, 0, 31));

        $headers = ['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock', 'Status', 'Created At'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue("{$columns[$index]}1", $header);
        }

        // Header Styling
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
            $sheet->setCellValue("E{$rowNumber}", $stock->quantity);
            $sheet->setCellValue("F{$rowNumber}", $stock->unit);
            $sheet->setCellValue("G{$rowNumber}", $stock->price ?? '-');
            $sheet->setCellValue("H{$rowNumber}", $stock->min_stock);
            $sheet->setCellValue("I{$rowNumber}", StockStatus::from($stock->status)->label());
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
    public function exportToResponse(Collection $stocks, string $filename = 'stok.xlsx'): StreamedResponse
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
     * Import stocks from an uploaded spreadsheet file path.
     *
     * @return array{imported: int, errors: array<int, string>}
     */
    public function import(string $filePath, int $userId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $imported = 0;
        $errors = [];
        $recordsToInsert = [];
        $now = Carbon::now();

        foreach (array_slice($rows, 1) as $row) {
            $name = trim((string) ($row['B'] ?? ''));

            // Lewati baris kosong
            if ($name === '') {
                continue;
            }

            $sku = trim((string) ($row['C'] ?? ''));
            $description = trim((string) ($row['D'] ?? ''));
            $unit = trim((string) ($row['F'] ?? ''));
            $priceRaw = trim((string) ($row['G'] ?? ''));

            $quantity = max(0, (int) ($row['E'] ?? 0));
            $minStock = is_numeric($row['H'] ?? null) ? max(0, (int) $row['H']) : 5;

            $recordsToInsert[] = [
                'user_id' => $userId,
                'name' => substr($name, 0, 255),
                'sku' => in_array($sku, ['', '-'], true) ? null : substr($sku, 0, 255),
                'description' => in_array($description, ['', '-'], true) ? null : $description,
                'quantity' => $quantity,
                'unit' => $unit === '' ? 'pcs' : substr($unit, 0, 255),
                'price' => is_numeric($priceRaw) ? $priceRaw : null,
                'min_stock' => $minStock,
                'status' => StockStatus::fromQuantity($quantity, $minStock)->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $imported++;
        }

        if (!empty($recordsToInsert)) {
            $this->stockRepository->insertMany($recordsToInsert);
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }
}

