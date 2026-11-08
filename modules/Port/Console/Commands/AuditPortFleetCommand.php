<?php

declare(strict_types=1);

namespace Modules\Port\Console\Commands;

use Illuminate\Console\Command;
use Modules\Port\Application\Services\PortFleetLedgerService;
use Modules\Port\Domain\Models\FleetLedgerEntry;

/**
 * Command Audit Kelaikan & Integritas Finansial Armada Maritim.
 *
 * Mendukung verifikasi balance double-entry dan deteksi manipulasi checksum (anti-fraud).
 */
final class AuditPortFleetCommand extends Command
{
    protected $signature = 'prt:fleet-audit {--corrupt : Simulasikan kerusakan integritas pembukuan untuk uji sabotase}';

    protected $description = 'Audit integritas buku besar armada dan verifikasi keseimbangan double-entry maritim';

    public function handle(PortFleetLedgerService $ledgerService): int
    {
        $this->info('Memulai audit integritas buku besar armada maritim (prt_fleet_ledger_entries)...');

        $isCorruptSimulated = (bool) $this->option('corrupt');

        if ($isCorruptSimulated) {
            $this->warn('[SIMULASI SABOTASE] Merusak sementara checksum entri terakhir untuk pengujian pagar...');
            $entry = FleetLedgerEntry::latest('id')->first();
            if ($entry) {
                $entry->update(['checksum' => 'CORRUPTED_HASH_VALUE_FAIL']);
            } else {
                // Buat dummy corrupted entry jika tabel kosong
                FleetLedgerEntry::create([
                    'entry_code' => 'FLT-CORRUPT-TEST',
                    'account_code' => PortFleetLedgerService::ACC_EXPENSE_BUNKER,
                    'entry_type' => 'DEBIT',
                    'amount' => 9999.0,
                    'currency' => 'USD',
                    'reference_type' => 'SABOTAGE_TEST',
                    'reference_id' => 1,
                    'description' => 'Entri simulasi sabotase audit',
                    'checksum' => 'INVALID_CHECKSUM',
                ]);
            }
        }

        $audit = $ledgerService->auditLedgerIntegrity();

        $this->line("Total Entri Buku Besar : {$audit['total_entries']}");
        $this->line('Total Debit (USD)      : '.number_format($audit['total_debit'], 2));
        $this->line('Total Credit (USD)     : '.number_format($audit['total_credit'], 2));
        $this->line('Status Keseimbangan    : '.($audit['is_balanced'] ? '<fg=green>SEIMBANG</>' : '<fg=red>TIDAK SEIMBANG</>'));
        $this->line("Entri Rusak / Corrupt  : {$audit['corrupted_count']}");

        if (! $audit['is_balanced'] || $audit['corrupted_count'] > 0) {
            $this->error('AUDIT GAGAL: Terdeteksi ketidakseimbangan atau korupsi integritas data finansial armada maritim!');

            return Command::FAILURE;
        }

        $this->info('AUDIT BERHASIL: Seluruh entri buku besar armada maritim valid, seimbang, dan lolos uji integritas.');

        return Command::SUCCESS;
    }
}
