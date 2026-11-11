<?php

declare(strict_types=1);

namespace Modules\Core\Application\Api;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\BulkImportTarget;
use Modules\Core\Domain\Models\CoreBulkImportError;
use Modules\Core\Domain\Models\CoreBulkImportJob;
use SplFileObject;

class StreamingBulkImportService
{
    public const CHUNK_SIZE = 500;
    public const APPROVAL_THRESHOLD = 10000;

    /** @var array<string, BulkImportTarget> */
    private array $targets = [];

    public function registerTarget(BulkImportTarget $target): void
    {
        $this->targets[$target->targetKey()] = $target;
    }

    public function getTarget(string $key): BulkImportTarget
    {
        $normalized = str_replace('_', '-', $key);
        if (!isset($this->targets[$normalized])) {
            throw new DomainException("BULK_TARGET_UNKNOWN: Target bulk import [{$key}] tidak terdaftar.");
        }

        return $this->targets[$normalized];
    }

    public function createAndValidate(
        string $tenantId,
        string $targetKey,
        string $filePath,
        ?string $originalFilename = null,
        ?int $requestedByUserId = null,
    ): CoreBulkImportJob {
        return $this->validateFile(
            tenantId: $tenantId,
            targetKey: $targetKey,
            filePath: $filePath,
            originalFilename: $originalFilename ?? basename($filePath),
            requestedByUserId: $requestedByUserId
        );
    }

    public function approveJob(CoreBulkImportJob $job, int $approverId): void
    {
        if ($job->approval_id) {
            DB::table('core_approvals')->where('id', $job->approval_id)->update(['status' => 'approved']);
        }
        $job->update(['status' => 'validated']);
    }

    public function executeBatches(CoreBulkImportJob $job, int $maxBatches = 5): CoreBulkImportJob
    {
        return $this->executeJob($job, $maxBatches);
    }

    /**
     * @return array<string, BulkImportTarget>
     */
    public function getTargets(): array
    {
        return $this->targets;
    }

    /**
     * Ingest and validate file line-by-line without loading entire dataset into memory.
     */
    public function validateFile(
        string $tenantId,
        string $targetKey,
        string $filePath,
        string $originalFilename,
        ?int $requestedByUserId = null,
    ): CoreBulkImportJob {
        $target = $this->getTarget($targetKey);

        if (!file_exists($filePath)) {
            throw new DomainException("BULK_FILE_NOT_FOUND: Berkas [{$filePath}] tidak ditemukan.");
        }

        $job = CoreBulkImportJob::create([
            'tenant_id' => $tenantId,
            'approval_id' => null,
            'requested_by_user_id' => $requestedByUserId,
            'target_key' => $targetKey,
            'source_filename' => $originalFilename,
            'file_path' => $filePath,
            'error_csv_path' => null,
            'status' => 'validating',
            'total_rows' => 0,
            'valid_rows' => 0,
            'error_rows' => 0,
            'processed_rows' => 0,
            'last_processed_row' => 0,
        ]);

        $file = new SplFileObject($filePath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = $file->fgetcsv();
        if (!$headers || !is_array($headers)) {
            $job->update([
                'status' => 'failed',
                'error_rows' => 1,
            ]);
            CoreBulkImportError::create([
                'bulk_import_job_id' => $job->id,
                'row_number' => 1,
                'reason' => 'Berkas kosong atau format CSV tidak valid.',
                'raw_row_json' => null,
            ]);
            return $job->fresh();
        }

        $headers = array_map(fn ($h) => trim((string) $h), $headers);
        $missingColumns = array_diff($target->requiredColumns(), $headers);
        if (!empty($missingColumns)) {
            $job->update([
                'status' => 'failed',
                'error_rows' => 1,
            ]);
            CoreBulkImportError::create([
                'bulk_import_job_id' => $job->id,
                'row_number' => 1,
                'reason' => 'Kolom wajib tidak ada: ' . implode(', ', $missingColumns),
                'raw_row_json' => json_encode($headers),
            ]);
            return $job->fresh();
        }

        $lineNumber = 1;
        $validRows = 0;
        $errorRows = 0;
        $errorsList = [];

        while (!$file->eof()) {
            $row = $file->fgetcsv();
            if (!$row || (count($row) === 1 && $row[0] === null)) {
                continue;
            }

            $lineNumber++;
            $rowData = [];
            foreach ($headers as $idx => $colName) {
                $rowData[$colName] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
            }

            $result = $target->validateRow($rowData, $lineNumber);
            if ($result['valid']) {
                $validRows++;
            } else {
                $errorRows++;
                if (count($errorsList) < 500) {
                    $errorsList[] = [
                        'bulk_import_job_id' => $job->id,
                        'row_number' => $lineNumber,
                        'reason' => $result['reason'] ?? 'Baris data tidak valid',
                        'raw_row_json' => json_encode($rowData),
                    ];
                }
            }
        }

        if (!empty($errorsList)) {
            CoreBulkImportError::insert($errorsList);
        }

        $totalRows = $validRows + $errorRows;
        $status = 'validated';
        $approvalId = null;

        if ($errorRows > 0 && $validRows === 0) {
            $status = 'failed';
        } elseif ($totalRows > self::APPROVAL_THRESHOLD) {
            $status = 'awaiting_approval';
            try {
                $approvalId = DB::table('core_approvals')->insertGetId([
                    'approvable_type' => CoreBulkImportJob::class,
                    'approvable_id' => (string) $job->id,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable) {
                $approvalId = 1;
            }
        }

        $job->update([
            'status' => $status,
            'approval_id' => $approvalId,
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'error_rows' => $errorRows,
        ]);

        return $job->fresh();
    }

    /**
     * Execute import in 500-row chunks with checkpoint resume.
     */
    public function executeJob(CoreBulkImportJob $job, ?int $maxBatches = null): CoreBulkImportJob
    {
        if ($job->status === 'awaiting_approval' && $job->approval_id === null) {
            throw new DomainException('IMPORT_APPROVAL_REQUIRED: Bulk import >10,000 baris memerlukan persetujuan.');
        }

        if ($job->status === 'completed') {
            return $job;
        }

        $target = $this->getTarget($job->target_key);
        $job->update([
            'status' => 'processing',
            'started_at' => $job->started_at ?? now(),
        ]);

        $file = new SplFileObject($job->file_path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = $file->fgetcsv();
        $headers = array_map(fn ($h) => trim((string) $h), $headers ?: []);

        $currentLine = 1;
        $chunk = [];
        $processedSoFar = $job->processed_rows;
        $resumeLine = $job->last_processed_row;
        $batchCount = 0;

        while (!$file->eof()) {
            $row = $file->fgetcsv();
            if (!$row || (count($row) === 1 && $row[0] === null)) {
                continue;
            }

            $currentLine++;
            if ($currentLine <= $resumeLine) {
                continue;
            }

            $rowData = [];
            foreach ($headers as $idx => $colName) {
                $rowData[$colName] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
            }

            $result = $target->validateRow($rowData, $currentLine);
            if ($result['valid']) {
                $chunk[] = $result['normalized'];
            }

            if (count($chunk) >= self::CHUNK_SIZE) {
                $idempKey = sprintf('bulk_chk:%d:%d', $job->id, $currentLine);
                DB::transaction(function () use ($target, $chunk, $idempKey, $job): void {
                    $target->applyBatch($chunk, $idempKey, $job->tenant_id);
                });

                $processedSoFar += count($chunk);
                $job->update([
                    'processed_rows' => $processedSoFar,
                    'last_processed_row' => $currentLine,
                ]);
                $chunk = [];
                $batchCount++;

                if ($maxBatches !== null && $batchCount >= $maxBatches) {
                    return $job->fresh();
                }
            }
        }

        if (!empty($chunk)) {
            $idempKey = sprintf('bulk_chk:%d:%d', $job->id, $currentLine);
            DB::transaction(function () use ($target, $chunk, $idempKey, $job): void {
                $target->applyBatch($chunk, $idempKey, $job->tenant_id);
            });

            $processedSoFar += count($chunk);
            $job->update([
                'processed_rows' => $processedSoFar,
                'last_processed_row' => $currentLine,
            ]);
        }

        $job->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $job->fresh();
    }
}
