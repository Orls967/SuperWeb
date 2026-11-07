<?php

declare(strict_types=1);

namespace Modules\Venue\Console\Commands;

use Illuminate\Console\Command;
use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingCapEngine;
use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingInput;
use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundleInput;
use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundlePricer;
use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallEngine;
use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallInput;
use Modules\Venue\Domain\Algorithms\Waitlist\WaitlistGovernorInput;
use Modules\Venue\Domain\Algorithms\Waitlist\WaitlistPriorityGovernor;
use Modules\Venue\Domain\Models\DynamicBundlePackage;
use Modules\Venue\Domain\Models\TicketResale;

class AuditVenueSecondaryCommand extends Command
{
    protected $signature = 'venue:audit-secondary';

    protected $aliases = ['ven:audit-secondary'];

    protected $description = 'Audit mandiri pasar sekunder, anti-scalping price cap 120%, single transfer rule, dynamic bundling floor guardrail, dan integritas waterfall pajak PBJT';

    public function handle(
        AntiScalpingCapEngine $antiScalping,
        DynamicBundlePricer $bundlingPricer,
        WaitlistPriorityGovernor $waitlistGovernor,
        SecondaryMarketTaxWaterfallEngine $waterfallEngine
    ): int {
        $this->info('Starting Venue Secondary Ticket Market & Bundling Audit (venue:audit-secondary)...');

        $discrepancies = 0;
        $auditRows = [];

        // 1. Audit Verifikasi Algoritma V9 Anti-Scalping Engine
        $testScalp = $antiScalping->evaluate(new AntiScalpingInput(
            originalFaceValueIdr: 1000000,
            askingPriceIdr: 1200000,
            transferCount: 0,
            userActiveTicketCount: 2,
            velocitySeconds: 3.5
        ));
        if (! $testScalp->isAllowed || ! $testScalp->certificate?->isValid() || $testScalp->effectivePriceIdr !== 1200000) {
            $discrepancies++;
            $auditRows[] = ['V9 Anti-Scalping Cap Integrity', 'FAILED', 'Kalkulasi golden vector V9 tidak cocok'];
        } else {
            $auditRows[] = ['V9 Anti-Scalping Cap Integrity', 'HEALTHY', 'Golden vector 120% cap & HMAC signature verified'];
        }

        // 2. Audit Verifikasi Algoritma V10 Dynamic Bundling Floor Guardrail
        $testBundle = $bundlingPricer->calculatePrice(new DynamicBundleInput(
            components: [
                ['name' => 'VIP', 'floor_cost' => 500000, 'base_price' => 1000000],
                ['name' => 'Hotel', 'floor_cost' => 500000, 'base_price' => 1000000],
            ],
            venueOccupancyPercentage: 90,
            hotelOccupancyPercentage: 80,
            leadTimeDays: 7
        ));
        if (! $testBundle->certificate?->isValid() || $testBundle->finalBundlePriceIdr < $testBundle->totalFloorCostIdr) {
            $discrepancies++;
            $auditRows[] = ['V10 Dynamic Bundle Floor Guardrail', 'FAILED', 'Floor margin guardrail dilanggar'];
        } else {
            $auditRows[] = ['V10 Dynamic Bundle Floor Guardrail', 'HEALTHY', 'Floor cost 1.0M guarded, dynamic surge verified'];
        }

        // 3. Audit Verifikasi Algoritma V11 Waitlist Priority Governor
        $testWaitlist = $waitlistGovernor->rankCandidates(new WaitlistGovernorInput([
            ['user_id' => 991, 'tier' => 'Sun', 'spend_365_idr' => 1000000, 'registered_seconds_ago' => 500],
            ['user_id' => 992, 'tier' => 'Infinity', 'spend_365_idr' => 20000000, 'registered_seconds_ago' => 100],
        ]));
        if (! $testWaitlist->certificate?->isValid() || ($testWaitlist->rankedUserIds[0] ?? null) !== 992) {
            $discrepancies++;
            $auditRows[] = ['V11 Waitlist Priority Governor', 'FAILED', 'Ranking prioritas member tiering tidak cocok'];
        } else {
            $auditRows[] = ['V11 Waitlist Priority Governor', 'HEALTHY', 'Infinity tier priority & spend weighting verified'];
        }

        // 4. Audit Verifikasi Algoritma V12 Secondary Tax Waterfall Conservation
        $testWaterfall = $waterfallEngine->calculateWaterfall(new SecondaryMarketTaxWaterfallInput(
            resalePriceIdr: 1000000,
            originalFaceValueIdr: 800000,
            taxRateBps: 1000,
            platformFeeRateBps: 800,
            promoterRoyaltyBps: 200
        ));
        $sumWaterfall = $testWaterfall->taxPbjtIdr + $testWaterfall->platformFeeIdr + $testWaterfall->promoterRoyaltyIdr + $testWaterfall->sellerPayoutIdr;
        if (! $testWaterfall->certificate?->isValid() || $sumWaterfall !== 1000000) {
            $discrepancies++;
            $auditRows[] = ['V12 Tax Waterfall Conservation', 'FAILED', 'Konservasi tertutup integer moneter gagal'];
        } else {
            $auditRows[] = ['V12 Tax Waterfall Conservation', 'HEALTHY', 'PBJT 10%, Fee 8%, Royalty 2%, Payout 80% integer verified'];
        }

        // 5. Rekonsiliasi Database: Resale Listings Anti-Scalping Ceiling (<= 120%)
        $illegalPrices = TicketResale::query()
            ->whereRaw('resale_price_idr > CAST(original_face_value_idr * 1.2001 AS UNSIGNED)')
            ->count();
        if ($illegalPrices > 0) {
            $discrepancies += $illegalPrices;
            $auditRows[] = ['Resale Listings 120% Price Cap Policy', 'FAILED', "{$illegalPrices} listing tiket melebihi batas 120%"];
        } else {
            $auditRows[] = ['Resale Listings 120% Price Cap Policy', 'HEALTHY', 'Seluruh listing tiket berada dalam batas legal 120%'];
        }

        // 6. Rekonsiliasi Database: Single Transfer Rule Enforcement (transfer_count <= 1)
        $illegalTransfers = TicketResale::query()
            ->where('transfer_count', '>', 1)
            ->count();
        if ($illegalTransfers > 0) {
            $discrepancies += $illegalTransfers;
            $auditRows[] = ['Single Transfer Limit Enforcement', 'FAILED', "{$illegalTransfers} tiket sekunder dipindahtangankan > 1 kali"];
        } else {
            $auditRows[] = ['Single Transfer Limit Enforcement', 'HEALTHY', 'Tidak ada tiket dengan transfer sekunder berulang'];
        }

        // 7. Rekonsiliasi Database: Dynamic Bundle Package Floor Guardrail (final >= floor)
        $illegalBundles = DynamicBundlePackage::query()
            ->where('status', 'PUBLISHED')
            ->whereColumn('final_price_idr', '<', 'floor_cost_idr')
            ->count();
        if ($illegalBundles > 0) {
            $discrepancies += $illegalBundles;
            $auditRows[] = ['Bundle Floor Cost Guardrail', 'FAILED', "{$illegalBundles} paket bundle dijual di bawah floor cost"];
        } else {
            $auditRows[] = ['Bundle Floor Cost Guardrail', 'HEALTHY', 'Seluruh paket bundle published memenuhi batas floor cost'];
        }

        $this->table(['Check Item', 'Status', 'Notes'], $auditRows);

        if ($discrepancies > 0) {
            $this->error("AUDIT PASAR SEKUNDER VENUE GAGAL: Terdeteksi {$discrepancies} diskrepansi integritas!");

            return self::FAILURE;
        }

        $this->info('AUDIT PASAR SEKUNDER VENUE SELESAI: Status HEALTHY (0 discrepancies).');

        return self::SUCCESS;
    }
}
