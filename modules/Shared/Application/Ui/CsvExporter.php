<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CsvExporter
 *
 * Utilitas ekspor data tabel ke format CSV berstandar RFC 4180
 * dengan encoding UTF-8 BOM untuk kompatibilitas Microsoft Excel.
 */
class CsvExporter
{
    /**
     * Membentuk respons unduhan CSV streaming dari generator data.
     *
     * @param list<string> $headers
     * @param iterable<list<mixed>> $rows
     */
    public static function streamDownload(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Tulis UTF-8 BOM untuk Microsoft Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // Tulis Header
            fputcsv($handle, $headers);

            // Tulis Baris Data
            foreach ($rows as $row) {
                $sanitizedRow = array_map(function ($val): string {
                    if ($val === null) {
                        return '';
                    }
                    if (is_bool($val)) {
                        return $val ? 'Ya' : 'Tidak';
                    }

                    return (string) $val;
                }, $row);

                fputcsv($handle, $sanitizedRow);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }

    /**
     * Menghasilkan string mentah CSV untuk pengujian atau penyimpanan berkas.
     *
     * @param list<string> $headers
     * @param iterable<list<mixed>> $rows
     */
    public static function generateString(array $headers, iterable $rows): string
    {
        $handle = fopen('php://memory', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            $sanitizedRow = array_map(fn ($val) => $val === null ? '' : (string) $val, $row);
            fputcsv($handle, $sanitizedRow);
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }
}

