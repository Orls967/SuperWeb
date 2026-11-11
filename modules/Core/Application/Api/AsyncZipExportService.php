<?php

declare(strict_types=1);

namespace Modules\Core\Application\Api;

use DomainException;
use Illuminate\Support\Facades\URL;
use Modules\Core\Domain\Models\CoreBulkExportJob;
use ZipArchive;

class AsyncZipExportService
{
    public const MAX_ROWS = 50000;

    public const DOWNLOAD_URL_EXPIRY_MINUTES = 15;

    public function __construct(
        private readonly StreamingBulkImportService $importService,
    ) {}

    public function exportChunkedZip(
        string $tenantId,
        string $targetKey,
        int $maxRows = self::MAX_ROWS,
        ?int $requestedByUserId = null
    ): CoreBulkExportJob {
        return $this->export($tenantId, str_replace('_', '-', $targetKey), $requestedByUserId, $maxRows);
    }

    /**
     * Export rows to ZIP file without external composer packages.
     */
    public function export(
        string $tenantId,
        string $targetKey,
        ?int $requestedByUserId = null,
        int $maxRows = self::MAX_ROWS,
    ): CoreBulkExportJob {
        $target = $this->importService->getTarget($targetKey);

        $tempDir = storage_path('app/exports/'.$tenantId);
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $csvFilename = "{$targetKey}_{$timestamp}.csv";
        $csvPath = "{$tempDir}/{$csvFilename}";
        $zipFilename = "{$targetKey}_{$timestamp}.zip";
        $zipPath = "{$tempDir}/{$zipFilename}";

        $csvHandle = fopen($csvPath, 'w');
        if ($csvHandle === false) {
            throw new DomainException("EXPORT_FILE_ERROR: Gagal membuat berkas sementara di [{$csvPath}].");
        }

        $headers = $target->requiredColumns();
        fputcsv($csvHandle, $headers);

        $rowCount = 0;
        foreach ($target->exportRows($maxRows) as $row) {
            $csvRow = [];
            foreach ($headers as $col) {
                $val = $row[$col] ?? '';
                $csvRow[] = is_scalar($val) ? (string) $val : json_encode($val);
            }
            fputcsv($csvHandle, $csvRow);
            $rowCount++;
            if ($rowCount >= $maxRows) {
                break;
            }
        }
        fclose($csvHandle);

        // Compress into ZIP using native ext-zip
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($csvPath);
            throw new DomainException("EXPORT_ZIP_ERROR: Gagal membuat arsip ZIP di [{$zipPath}].");
        }

        $zip->addFile($csvPath, $csvFilename);
        $zip->close();
        @unlink($csvPath);

        $fileSha256 = hash_file('sha256', $zipPath) ?: '';

        return CoreBulkExportJob::create([
            'tenant_id' => $tenantId,
            'requested_by_user_id' => $requestedByUserId,
            'target_key' => $targetKey,
            'status' => 'completed',
            'total_rows' => $rowCount,
            'zip_path' => $zipPath,
            'file_sha256' => $fileSha256,
            'expires_at' => now()->addDays(7),
            'purged_at' => null,
        ]);
    }

    /**
     * Generate signed URL for downloading export file, valid for 15 minutes.
     */
    public function generateDownloadUrl(CoreBulkExportJob $job): string
    {
        return URL::temporarySignedRoute(
            'api.v2.bulk.export.download',
            now()->addMinutes(self::DOWNLOAD_URL_EXPIRY_MINUTES),
            ['job' => $job->id]
        );
    }

    /**
     * Purge expired export files.
     */
    public function purgeExpired(): int
    {
        $expiredJobs = CoreBulkExportJob::where('status', 'completed')
            ->whereNull('purged_at')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expiredJobs as $job) {
            if ($job->zip_path && file_exists($job->zip_path)) {
                @unlink($job->zip_path);
            }
            $job->update(['purged_at' => now()]);
            $count++;
        }

        return $count;
    }
}
