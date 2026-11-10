<?php

namespace App\Features\Stock\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StockExcelService
{
    public const HEADERS = ['name', 'sku', 'qty', 'unit', 'price', 'min_stock', 'status'];

    public function export(Collection $stocks): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADERS, null, 'A1');

        $row = 2;
        foreach ($stocks as $stock) {
            $sheet->fromArray([
                $stock->name,
                $stock->sku,
                $stock->quantity,
                $stock->unit,
                $stock->price,
                $stock->min_stock,
                $stock->status->value,
            ], null, 'A'.$row);
            $row++;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'stk');
        (new Xlsx($spreadsheet))->save($tmp);
        $content = (string) file_get_contents($tmp);
        unlink($tmp);

        return $content;
    }

    /** @return array<int, array<string, mixed>> */
    public function parse(string $path): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        array_shift($rows);

        $result = [];
        foreach ($rows as $row) {
            if (trim((string) ($row[0] ?? '')) === '') {
                continue;
            }

            $result[] = [
                'name' => (string) $row[0],
                'sku' => ($row[1] ?? '') !== '' ? (string) $row[1] : null,
                'quantity' => (int) ($row[2] ?? 0),
                'unit' => ($row[3] ?? '') !== '' ? (string) $row[3] : 'pcs',
                'price' => ($row[4] ?? '') !== '' ? $row[4] : null,
                'min_stock' => ($row[5] ?? '') !== '' ? (int) $row[5] : 5,
            ];
        }

        return $result;
    }
}
