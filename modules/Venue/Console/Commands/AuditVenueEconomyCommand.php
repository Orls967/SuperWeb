<?php

declare(strict_types=1);

namespace Modules\Venue\Console\Commands;

use Illuminate\Console\Command;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocationInput;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocator;
use Modules\Venue\Domain\Algorithms\ArtistDoorSplitCalculator;
use Modules\Venue\Domain\Algorithms\ArtistDoorSplitInput;
use Modules\Venue\Domain\Algorithms\MembershipTierEngine;
use Modules\Venue\Domain\Algorithms\MembershipTierInput;
use Modules\Venue\Domain\Algorithms\SponsorExposureInput;
use Modules\Venue\Domain\Algorithms\SponsorExposureValuator;
use Modules\Venue\Domain\Models\ArtistEngagement;
use Modules\Venue\Domain\Models\FestivalBundleOrder;
use Modules\Venue\Domain\Models\VenueMembership;

class AuditVenueEconomyCommand extends Command
{
    protected $signature = 'venue:audit-economy';

    protected $aliases = ['ven:audit-economy'];

    protected $description = 'Audit mandiri ekonomi venue: kontrak artis versus deal, alokasi bundle SSP multi-vendor, membership tiering, sponsor exposure KPI, dan konsistensi buku besar';

    public function handle(
        ArtistDoorSplitCalculator $artistCalc,
        BundleSspAllocator $bundleAlloc,
        MembershipTierEngine $tierEngine,
        SponsorExposureValuator $sponsorValuator
    ): int {
        $this->info('Starting Venue Night Economy & Multi-Vendor Audit (venue:audit-economy)...');

        $discrepancies = 0;
        $auditRows = [];

        // 1. Audit Verifikasi Algoritma V5 Artist Versus Deal
        $testArtistInput = new ArtistDoorSplitInput(
            dealType: 'versus',
            guaranteeIdr: 100_000_000,
            doorGrossIdr: 600_000_000,
            approvedExpensesIdr: 150_000_000,
            shareBps: 3_000,
            whtBps: 2_000,
            advancePaidIdr: 50_000_000,
            currency: 'IDR'
        );
        $artistRes = $artistCalc->calculate($testArtistInput);
        if (! $artistRes->certificate->isValid() || $artistRes->netPayableIdr !== 58_000_000) {
            $discrepancies++;
            $auditRows[] = ['V5 Artist Deal Integrity', 'FAILED', 'Kalkulasi golden vector V5 tidak cocok'];
        } else {
            $auditRows[] = ['V5 Artist Deal Integrity', 'HEALTHY', 'Golden vector & signature verified'];
        }

        // 2. Audit Verifikasi Algoritma V6 Bundle SSP Hamilton-Hare
        $testBundleInput = new BundleSspAllocationInput(
            bundlePriceIdr: 2_999_999,
            platformFeeBps: 500,
            components: [
                ['name' => 'pass', 'ssp_idr' => 1_800_000, 'vendor_id' => 'VEN-1'],
                ['name' => 'hotel', 'ssp_idr' => 1_500_000, 'vendor_id' => 'HTL-1'],
                ['name' => 'fnb', 'ssp_idr' => 400_000, 'vendor_id' => 'RES-1'],
                ['name' => 'shuttle', 'ssp_idr' => 300_000, 'vendor_id' => 'TRN-1'],
            ]
        );
        $bundleRes = $bundleAlloc->allocate($testBundleInput);
        $allocSum = array_sum(array_column($bundleRes->items, 'allocated_amount_idr'));
        if (! $bundleRes->certificate->isValid() || $allocSum !== 2_999_999) {
            $discrepancies++;
            $auditRows[] = ['V6 Bundle SSP Conservation', 'FAILED', "Total alokasi ({$allocSum}) != 2.999.999"];
        } else {
            $auditRows[] = ['V6 Bundle SSP Conservation', 'HEALTHY', 'Invarian konservasi terpenuhi tanpa residu'];
        }

        // 3. Audit Verifikasi Algoritma V7 Membership Tiering & Grace Period
        $testTierInput = new MembershipTierInput('infinity', 65_000_000, 15, true);
        $tierRes = $tierEngine->evaluate($testTierInput);
        if (! $tierRes->certificate->isValid() || ! $tierRes->inGracePeriod) {
            $discrepancies++;
            $auditRows[] = ['V7 Membership Tier Grace Period', 'FAILED', 'Logika grace period 80% tidak terpicu'];
        } else {
            $auditRows[] = ['V7 Membership Tier Grace Period', 'HEALTHY', 'Grace period & locker eligibility valid'];
        }

        // 4. Audit Verifikasi Algoritma V8 Sponsor Exposure KPI
        $testSponsorInput = new SponsorExposureInput(
            zoneDeliveries: [
                ['zone_name' => 'Main', 'footfall' => 40_000, 'dwell_weight_bps' => 10_000, 'screen_share_bps' => 10_000],
            ],
            targetImpressions: 50_000,
            cpmRateIdr: 50_000,
            milestoneBudgetAmountIdr: 10_000_000
        );
        $sponsorRes = $sponsorValuator->valuate($testSponsorInput);
        if (! $sponsorRes->certificate->isValid() || $sponsorRes->payoutRatioBps !== 8_000) {
            $discrepancies++;
            $auditRows[] = ['V8 Sponsor Exposure Valuation', 'FAILED', 'Pro-rata payout ratio tidak cocok'];
        } else {
            $auditRows[] = ['V8 Sponsor Exposure Valuation', 'HEALTHY', 'Weighted footfall & milestone payout verified'];
        }

        // 5. Rekonsiliasi Database: Bundle Order vs Total Alokasi
        $orders = FestivalBundleOrder::query()->with('allocations')->get();
        foreach ($orders as $order) {
            $sum = $order->allocations->sum('allocated_amount_idr');
            if ($sum !== $order->bundle_price_idr) {
                $discrepancies++;
                $auditRows[] = ["Order {$order->order_number} Allocations", 'FAILED', "Sum allocations ({$sum}) != Price ({$order->bundle_price_idr})"];
            }
        }

        // 6. Rekonsiliasi Database: Artist Engagement Net Payout Integrity
        $engagements = ArtistEngagement::query()->get();
        foreach ($engagements as $eng) {
            $expectedNet = max(0, $eng->gross_pay_idr - $eng->wht_idr - $eng->advance_paid_idr);
            if ($eng->net_paid_idr !== $expectedNet) {
                $discrepancies++;
                $auditRows[] = ["Artist {$eng->contract_number} Net Pay", 'FAILED', "Net ({$eng->net_paid_idr}) != Expected ({$expectedNet})"];
            }
        }

        // 7. Rekonsiliasi Database: Membership Bottle Locker Authorization
        $illegalLockers = VenueMembership::query()
            ->whereNotNull('bottle_locker_number')
            ->whereRaw("LOWER(tier) != 'infinity'")
            ->count();
        if ($illegalLockers > 0) {
            $discrepancies += $illegalLockers;
            $auditRows[] = ['Membership Bottle Locker Policy', 'FAILED', "{$illegalLockers} member non-infinity memiliki loker botol"];
        } else {
            $auditRows[] = ['Membership Bottle Locker Policy', 'HEALTHY', 'Seluruh alokasi loker botol sah untuk tier Infinity'];
        }

        // Cetak Tabel Hasil Audit
        $this->table(['Check Item', 'Status', 'Notes'], $auditRows);

        if ($discrepancies > 0) {
            $this->error("AUDIT EKONOMI VENUE GAGAL: Terdeteksi {$discrepancies} diskrepansi integritas!");

            return self::FAILURE;
        }

        $this->info('AUDIT EKONOMI VENUE SELESAI: Status HEALTHY (0 discrepancies).');

        return self::SUCCESS;
    }
}
