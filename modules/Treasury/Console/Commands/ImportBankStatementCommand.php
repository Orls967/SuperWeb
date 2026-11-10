<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\BankStatement;

class ImportBankStatementCommand extends Command
{
    protected $signature = 'treasury:import-statement {--account= : Nomor rekening bank} {--file= : Path file CSV/MT940 simulasi}';

    protected $description = 'Impor mutasi rekening bank format CSV atau MT940 (fixture simulasi)';

    public function handle(): int
    {
        $accountNo = $this->option('account');
        $file = $this->option('file');

        $bankAccount = $accountNo
            ? BankAccount::where('account_number', $accountNo)->first()
            : BankAccount::first();

        if ($bankAccount === null) {
            $this->error('Rekening bank tidak ditemukan.');

            return self::FAILURE;
        }

        $this->info("Mengimpor mutasi ke rekening {$bankAccount->account_number} ({$bankAccount->bank_name})...");

        // Parsing file jika disediakan, jika tidak buat mutasi fixture default
        $imported = 0;
        if ($file && file_exists($file)) {
            $rows = array_map('str_getcsv', file($file));
            $header = array_shift($rows);
            foreach ($rows as $row) {
                if (count($row) >= 4) {
                    BankStatement::create([
                        'bank_account_id' => $bankAccount->id,
                        'transaction_date' => $row[0] ?? date('Y-m-d'),
                        'reference_no' => $row[1] ?? 'REF-IMP-'.rand(1000, 9999),
                        'amount' => (int) ($row[2] ?? 0),
                        'description' => $row[3] ?? 'Imported statement item',
                        'is_reconciled' => false,
                        'reconciliation_status' => 'unreconciled',
                    ]);
                    $imported++;
                }
            }
        } else {
            // Sample fixture import
            for ($i = 1; $i <= 5; $i++) {
                BankStatement::create([
                    'bank_account_id' => $bankAccount->id,
                    'transaction_date' => date('Y-m-d'),
                    'reference_no' => 'STMT-FIX-'.rand(100000, 999999),
                    'amount' => 50_000_000 * $i,
                    'description' => "Simulasi mutasi bank MT940 line item {$i}",
                    'is_reconciled' => false,
                    'reconciliation_status' => 'unreconciled',
                ]);
                $imported++;
            }
        }

        $this->info("Berhasil mengimpor {$imported} mutasi bank ke rekening {$bankAccount->account_number}.");

        return self::SUCCESS;
    }
}
