<?php

declare(strict_types=1);

namespace Modules\Aviation\Exports;

abstract class BaseCsvExporter
{
    /**
     * Konversi larik baris tabular menjadi string CSV ber-BOM UTF-8.
     *
     * @param array<int, array<int, string>> $rows
     * @return string
     */
    protected function generateCsvString(array $rows): string
    {
        $handle = fopen('php://memory', 'r+');
        if ($handle === false) {
            return '';
        }

        // Tulis UTF-8 BOM untuk kompatibilitas aplikasi spreadsheet
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csvData = stream_get_contents($handle);
        fclose($handle);

        return $csvData !== false ? $csvData : '';
    }
}

