<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\RecognizeLoyaltyBreakageRevenueAction;
use Modules\Hotel\Application\Actions\ReconcileMultiLineLoyaltyLiabilityAction;
use Modules\Hotel\Domain\Services\HotelTravelPassTierMaintenanceService;

final class HotelNightAuditLoyaltyCommand extends Command
{
    protected $signature = 'hotel:night-audit-loyalty';

    protected $description = 'Menjalankan langkah audit loyalitas harian (evaluasi tier, kadaluwarsa batch, rekonsiliasi liabilitas)';

    public function handle(
        HotelTravelPassTierMaintenanceService $tierMaintenance,
        RecognizeLoyaltyBreakageRevenueAction $breakageAction,
        ReconcileMultiLineLoyaltyLiabilityAction $reconciliationAction
    ): int {
        $this->info('=== HOTEL NIGHT AUDIT: LOYALTY & TRAVEL PASS ENGINE ===');

        // Step 1: Tier Maintenance
        $this->line('1. Menjalankan evaluasi tingkat keanggotaan global (tier maintenance)...');
        $tierResults = $tierMaintenance->evaluateAllPasses();
        $this->info("   Evaluasi selesai: {$tierResults['total_scanned']} akun dipindai, {$tierResults['upgraded']} dinaikkan, {$tierResults['downgraded']} disesuaikan.");

        // Step 2: Points Breakage & Expiration
        $this->line('2. Memindai batch poin kadaluwarsa dan mengakui pendapatan breakage IFRS 15...');
        $breakage = $breakageAction->execute();
        $this->info("   Breakage diakui: {$breakage->totalExpiredPts} PTS kadaluwarsa, IDR ".number_format($breakage->breakageRevenueIdr).' diakui sebagai pendapatan.');

        // Step 3: Liability Ledger Reconciliation
        $this->line('3. Menjalankan audit rekonsiliasi saldo liabilitas internal vs ledger...');
        $reconciliation = $reconciliationAction->execute();
        if ($reconciliation['is_balanced']) {
            $this->info('   ✓ Liabilitas seimbang! Total PTS: '.number_format($reconciliation['total_active_points']).', Nilai: IDR '.number_format($reconciliation['total_liability_idr']));
        } else {
            $this->warn('   ! Selisih audit terdeteksi: IDR '.number_format($reconciliation['discrepancy_idr']));
        }

        $this->info('Night audit loyalitas selesai dengan sukses.');

        return self::SUCCESS;
    }
}
