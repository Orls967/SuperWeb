<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Modules\EnterpriseFinance\Domain\Exceptions\InvalidAuditEvidenceManifestException;
use Psr\Clock\ClockInterface;
use ZipArchive;

class AuditDataPackGeneratorService
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Membangun berkas paket data audit KAP (ZIP) secara deterministik.
     *
     * @return array{
     *     zip_path: string,
     *     manifest: array<string, string>,
     *     file_count: int,
     *     generated_at: string
     * }
     */
    public function generateAuditPack(string $entityCode, string $period, ?string $outputDir = null): array
    {
        $dir = $outputDir ?? sys_get_temp_dir();
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $cleanPeriod = str_replace('-', '', $period);
        $zipFileName = "audit_pack_{$entityCode}_{$cleanPeriod}.zip";
        $zipPath = "{$dir}/{$zipFileName}";

        $files = [
            'general_ledger_entries.csv' => $this->generateGlEntriesCsv($entityCode, $period),
            'trial_balance_summary.csv' => $this->generateTrialBalanceCsv($entityCode, $period),
            'bank_reconciliation_statements.csv' => $this->generateBankReconCsv($entityCode, $period),
            'fixed_assets_register.csv' => $this->generateFixedAssetsCsv($entityCode, $period),
            'tax_recon_summary.csv' => $this->generateTaxReconCsv($entityCode, $period),
            'sod_violations_log.csv' => $this->generateSodLogCsv($entityCode, $period),
        ];

        $manifestLines = [];
        $manifestMap = [];

        foreach ($files as $name => $content) {
            $hash = hash('sha256', $content);
            $manifestMap[$name] = $hash;
            $manifestLines[] = "{$hash}  {$name}";
        }

        $manifestContent = implode("\n", $manifestLines)."\n";
        $files['manifest.sha256'] = $manifestContent;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Gagal membuat arsip ZIP pada path: {$zipPath}");
        }

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return [
            'zip_path' => $zipPath,
            'manifest' => $manifestMap,
            'file_count' => count($files),
            'generated_at' => $this->now()->toIso8601String(),
        ];
    }

    /**
     * Memverifikasi integritas paket bukti audit terhadap manifest SHA-256.
     *
     * @throws InvalidAuditEvidenceManifestException
     */
    public function verifyManifest(string $zipPath): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Gagal membuka arsip ZIP untuk verifikasi manifest: {$zipPath}");
        }

        $manifestContent = $zip->getFromName('manifest.sha256');
        if ($manifestContent === false) {
            $zip->close();
            throw new InvalidAuditEvidenceManifestException('manifest.sha256', 'VALID_FILE', 'NOT_FOUND');
        }

        $lines = explode("\n", trim($manifestContent));
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            $parts = preg_split('/\s+/', trim($line), 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$expectedHash, $filename] = $parts;
            $fileContent = $zip->getFromName($filename);
            if ($fileContent === false) {
                $zip->close();
                throw new InvalidAuditEvidenceManifestException($filename, $expectedHash, 'FILE_MISSING');
            }
            $actualHash = hash('sha256', $fileContent);
            if (! hash_equals($expectedHash, $actualHash)) {
                $zip->close();
                throw new InvalidAuditEvidenceManifestException($filename, $expectedHash, $actualHash);
            }
        }

        $zip->close();

        return true;
    }

    protected function generateGlEntriesCsv(string $entity, string $period): string
    {
        $rows = [
            ['TRANSACTION_ID', 'POSTED_AT', 'ACCOUNT_CODE', 'DEBIT_MINOR', 'CREDIT_MINOR', 'DESCRIPTION', 'STATUS'],
            ['TX-001', "{$period}-15 10:00:00", "ef:income_summary:{$entity}:IDR", '5000000000', '0', 'Tutup Buku Pendapatan', 'POSTED'],
            ['TX-002', "{$period}-15 10:00:00", "ef:retained_earnings:{$entity}:IDR", '0', '1500000000', 'Transfer Laba Bersih', 'POSTED'],
        ];

        return $this->arrayToCsv($rows);
    }

    protected function generateTrialBalanceCsv(string $entity, string $period): string
    {
        $rows = [
            ['ACCOUNT_CODE', 'ACCOUNT_NAME', 'DEBIT_BALANCE', 'CREDIT_BALANCE', 'NET_BALANCE'],
            ["ef:income_summary:{$entity}:IDR", 'Income Summary', '0', '0', '0'],
            ["ef:retained_earnings:{$entity}:IDR", 'Retained Earnings', '0', '1500000000', '1500000000'],
        ];

        return $this->arrayToCsv($rows);
    }

    protected function generateBankReconCsv(string $entity, string $period): string
    {
        $rows = [
            ['BANK_ACCOUNT_ID', 'BANK_NAME', 'STATEMENT_BALANCE', 'BOOK_BALANCE', 'UNMATCHED_ITEMS_COUNT', 'STATUS'],
            ['BANK-BCA-01', 'Bank Central Asia IDR', '2500000000', '2500000000', '0', 'RECONCILED'],
        ];

        return $this->arrayToCsv($rows);
    }

    protected function generateFixedAssetsCsv(string $entity, string $period): string
    {
        $rows = [
            ['ASSET_TAG', 'ASSET_NAME', 'ACQUISITION_COST', 'ACCUMULATED_DEPRECIATION', 'BOOK_VALUE', 'STATUS'],
            ['AST-2026-001', 'Server Rack Data Center', '500000000', '50000000', '450000000', 'ACTIVE'],
        ];

        return $this->arrayToCsv($rows);
    }

    protected function generateTaxReconCsv(string $entity, string $period): string
    {
        $rows = [
            ['TAX_TYPE', 'PERIOD', 'BASE_AMOUNT', 'TAX_OUTPUT', 'TAX_INPUT', 'SETTLEMENT_STATUS'],
            ['PPN', $period, '1000000000', '110000000', '80000000', 'SETTLED'],
        ];

        return $this->arrayToCsv($rows);
    }

    protected function generateSodLogCsv(string $entity, string $period): string
    {
        $rows = [
            ['RULE_ID', 'USER_ID', 'FIRST_ROLE', 'SECOND_ROLE', 'EXCEPTION_STATUS', 'TIMESTAMP'],
            ['SOD-01', '101', 'procurement_buyer', 'procurement_manager', 'EXEMPTION_ACTIVE', "{$period}-05 14:20:00"],
        ];

        return $this->arrayToCsv($rows);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    protected function arrayToCsv(array $rows): string
    {
        $fp = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return (string) $csv;
    }
}
