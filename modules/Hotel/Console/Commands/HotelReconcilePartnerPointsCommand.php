<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Domain\Models\HtlAlliancePartner;
use Modules\Hotel\Domain\Services\HotelAllianceSettlementBatchProcessor;

final class HotelReconcilePartnerPointsCommand extends Command
{
    protected $signature = 'hotel:reconcile-partner-points {--partner= : Optional specific partner code}';

    protected $description = 'Melakukan batch clearing dan netting settlement untuk transfer poin aliansi partner maskapai';

    public function handle(HotelAllianceSettlementBatchProcessor $processor): int
    {
        $this->info('Memulai rekonsiliasi netting settlement poin aliansi...');

        $partnerCode = $this->option('partner');
        $query = HtlAlliancePartner::query();
        if ($partnerCode) {
            $query->where('partner_code', $partnerCode);
        }

        $partners = $query->get();
        if ($partners->isEmpty()) {
            $this->warn('Tidak ada mitra aliansi yang ditemukan.');

            return self::SUCCESS;
        }

        foreach ($partners as $partner) {
            $this->line("Memproses partner: {$partner->partner_name} ({$partner->partner_code})...");
            try {
                $result = $processor->executePartnerSettlementBatch($partner->partner_code);
                $this->info("✓ Settlement batch berhasil dibuat: {$result['batch_id']}");
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Batch ID', $result['batch_id']],
                        ['Total Transaksi', $result['total_transfers']],
                        ['Total Poin Ditransfer', number_format($result['total_points_debited'])],
                        ['Total Unit Partner', number_format($result['total_partner_units'])],
                        ['Net Payable (IDR)', number_format($result['net_settlement_idr'])],
                    ]
                );
            } catch (\Throwable $e) {
                $this->warn("! Partner {$partner->partner_code}: {$e->getMessage()}");
            }
        }

        $this->info('Proses rekonsiliasi aliansi partner selesai.');

        return self::SUCCESS;
    }
}
