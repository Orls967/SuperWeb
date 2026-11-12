<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Treasury\Application\Services\BankStatementImportService;
use Modules\Treasury\Domain\Models\BankAccount;

class ImportBankStatementCommand extends Command
{
    protected $signature = 'treasury:import-statement {--account= : Nomor rekening bank} {--file= : Path file CSV/MT940 simulasi} {--format= : Format file (CSV atau MT940)}';

    protected $description = 'Impor mutasi rekening bank format CSV atau MT940 (fixture simulasi) secara idempoten';

    public function handle(BankStatementImportService $importService): int
    {
        $accountNo = $this->option('account');
        $file = $this->option('file');
        $formatOpt = strtoupper((string) ($this->option('format') ?: ''));

        $bankAccount = $accountNo
            ? BankAccount::where('account_number', $accountNo)->first()
            : BankAccount::first();

        if ($bankAccount === null) {
            $this->error('Rekening bank tidak ditemukan.');

            return self::FAILURE;
        }

        $this->info("Mengimpor mutasi ke rekening {$bankAccount->account_number} ({$bankAccount->bank_name})...");

        if ($file && file_exists((string) $file)) {
            $rawContent = (string) file_get_contents((string) $file);
            $isMt940 = $formatOpt === 'MT940' || str_contains($rawContent, ':20:') || str_contains($rawContent, ':61:');

            if ($isMt940) {
                $res = $importService->importMt940Content($bankAccount, $rawContent);
            } else {
                $rows = array_map('str_getcsv', file((string) $file) ?: []);
                $res = $importService->importCsvRows($bankAccount, $rows);
            }
        } else {
            // Fixture MT940 deterministik (idempoten bila dijalankan ulang)
            $sampleMt940 = implode("\n", [
                ':20:STMT-MT940-DEFAULT-001',
                ':25:'.$bankAccount->account_number,
                ':28C:00001/001',
                ':60F:C261101IDR500000000,00',
                ':61:2611011101C50000000,00NTRFSTMT-FIX-100001',
                ':86:Simulasi mutasi bank MT940 line item 1',
                ':61:2611021102C100000000,00NTRFSTMT-FIX-100002',
                ':86:Simulasi mutasi bank MT940 line item 2',
                ':61:2611031103C150000000,00NTRFSTMT-FIX-100003',
                ':86:Simulasi mutasi bank MT940 line item 3',
                ':61:2611041104C200000000,00NTRFSTMT-FIX-100004',
                ':86:Simulasi mutasi bank MT940 line item 4',
                ':61:2611051105C250000000,00NTRFSTMT-FIX-100005',
                ':86:Simulasi mutasi bank MT940 line item 5',
                ':62F:C261105IDR1250000000,00',
            ]);

            $res = $importService->importMt940Content($bankAccount, $sampleMt940);
        }

        $imported = $res['imported_count'];
        $skipped = $res['skipped_duplicates'];
        $this->info("Berhasil mengimpor {$imported} mutasi bank (dilewati duplikat: {$skipped}) ke rekening {$bankAccount->account_number}.");

        return self::SUCCESS;
    }
}
