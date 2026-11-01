<?php

declare(strict_types=1);

namespace Tests\Acceptance\Media;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Med\Application\Services\IpLicensingService;
use Modules\Med\Application\Services\IpRightsRegistryService;
use Modules\Med\Application\Services\RoyaltyWaterfallService;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Med\Domain\Models\MedRoyaltyStatementLine;
use RuntimeException;
use Tests\TestCase;

class RoyaltyWaterfallAndSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected RoyaltyWaterfallService $waterfallService;

    protected IpRightsRegistryService $rightsService;

    protected IpLicensingService $licensingService;

    protected MedIpAsset $testIp;

    protected MedIpLicense $testLicense;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->waterfallService = app(RoyaltyWaterfallService::class);
        $this->rightsService = app(IpRightsRegistryService::class);
        $this->licensingService = app(IpLicensingService::class);

        $this->testIp = $this->rightsService->registerAsset([
            'ip_code' => 'IP-ROYALTY-001',
            'title' => 'Waterfall Symphony',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
        ]);

        $this->rightsService->setRightsShares([
            'ip_id' => $this->testIp->id,
            'rights_type' => 'STREAMING',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-COMPOSER', 'share_bps' => 5000], // 50%
                ['rightsholder_party_id' => 'PARTY-LYRICIST', 'share_bps' => 3000], // 30%
                ['rightsholder_party_id' => 'PARTY-PRODUCER', 'share_bps' => 2000], // 20%
            ],
        ]);

        $this->testLicense = $this->licensingService->grantLicense([
            'ip_id' => $this->testIp->id,
            'licensee_entity_id' => 'PARTY-STREAMER-01',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'GLOBAL',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'minimum_guarantee_minor' => 0,
            'royalty_rate_pct' => 15.0,
            'idempotency_key' => 'LIC:ROYALTY:01',
        ]);
    }

    public function test_waterfall_calculates_distribution_fee_and_net_pool(): void
    {
        // Gross 100,000,000 IDR, Fee 15% (15,000,000 IDR), Recoupment 0 -> Net Pool 85,000,000 IDR
        $statement = $this->waterfallService->settleRoyalty([
            'license_id' => $this->testLicense->id,
            'billing_period' => '2026-10',
            'gross_revenue_minor' => 100_000_000,
            'distribution_fee_bps' => 1500, // 15%
            'recoupment_minor' => 0,
            'idempotency_key' => 'STMT:2026-10:01',
        ]);

        expect($statement->gross_revenue_minor)->toBe(100_000_000)
            ->and($statement->total_distribution_fee_minor)->toBe(15_000_000)
            ->and($statement->total_royalty_distributed_minor)->toBe(85_000_000);

        $lines = MedRoyaltyStatementLine::where('statement_id', $statement->id)->get()->keyBy('rightsholder_party_id');
        expect($lines['PARTY-COMPOSER']->net_payable_minor)->toBe(42_500_000)
            ->and($lines['PARTY-LYRICIST']->net_payable_minor)->toBe(25_500_000)
            ->and($lines['PARTY-PRODUCER']->net_payable_minor)->toBe(17_000_000);
    }

    public function test_waterfall_penny_absorption_remainder_absorbed_by_last_row_exact_1_rupiah(): void
    {
        // 3 equal rightsholders: 3,333 bps, 3,333 bps, 3,334 bps = 10,000 bps
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-PENNY-001',
            'title' => 'Penny Song',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
        ]);

        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'STREAMING',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-P1', 'share_bps' => 3333],
                ['rightsholder_party_id' => 'PARTY-P2', 'share_bps' => 3333],
                ['rightsholder_party_id' => 'PARTY-P3', 'share_bps' => 3334],
            ],
        ]);

        $license = $this->licensingService->grantLicense([
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'PARTY-PENNY-STREAMER',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'GLOBAL',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'idempotency_key' => 'LIC:PENNY:01',
        ]);

        // Net Pool: 100 IDR (Fee 0)
        // 100 * 3333 / 10000 = 33.33 -> 33
        // 100 * 3333 / 10000 = 33.33 -> 33
        // 100 * 3334 / 10000 = 33.34 -> 33 + remainder 1 = 34
        // Sum MUST be exactly 100 IDR!
        $statement = $this->waterfallService->settleRoyalty([
            'license_id' => $license->id,
            'billing_period' => '2026-10',
            'gross_revenue_minor' => 100,
            'distribution_fee_bps' => 0,
            'recoupment_minor' => 0,
            'idempotency_key' => 'STMT:PENNY:01',
        ]);

        $linesSum = (int) MedRoyaltyStatementLine::where('statement_id', $statement->id)->sum('net_payable_minor');
        expect($linesSum)->toBe(100)
            ->and($statement->total_royalty_distributed_minor)->toBe(100);
    }

    public function test_advance_recoupment_across_multiple_periods(): void
    {
        // Register advance of 50,000,000 IDR for composer
        $this->waterfallService->recordAdvance([
            'license_id' => $this->testLicense->id,
            'rightsholder_party_id' => 'PARTY-COMPOSER',
            'advance_amount_minor' => 50_000_000,
            'idempotency_key' => 'ADV:REC:01',
        ]);

        // Period 1: Composer share is 30,000,000 IDR. Full 30,000,000 recouped -> net payable 0 IDR, remaining advance 20,000,000 IDR.
        $stmt1 = $this->waterfallService->settleRoyalty([
            'license_id' => $this->testLicense->id,
            'billing_period' => '2026-09',
            'gross_revenue_minor' => 60_000_000, // Composer gets 50% = 30M
            'distribution_fee_bps' => 0,
            'idempotency_key' => 'STMT:REC:P1',
        ]);

        $composerLine1 = MedRoyaltyStatementLine::where('statement_id', $stmt1->id)->where('rightsholder_party_id', 'PARTY-COMPOSER')->first();
        expect($composerLine1->recoupment_deducted_minor)->toBe(30_000_000)
            ->and($composerLine1->net_payable_minor)->toBe(0);

        // Period 2: Composer share is 30,000,000 IDR. Remaining 20,000,000 recouped -> net payable 10,000,000 IDR, remaining advance 0.
        $stmt2 = $this->waterfallService->settleRoyalty([
            'license_id' => $this->testLicense->id,
            'billing_period' => '2026-10',
            'gross_revenue_minor' => 60_000_000,
            'distribution_fee_bps' => 0,
            'idempotency_key' => 'STMT:REC:P2',
        ]);

        $composerLine2 = MedRoyaltyStatementLine::where('statement_id', $stmt2->id)->where('rightsholder_party_id', 'PARTY-COMPOSER')->first();
        expect($composerLine2->recoupment_deducted_minor)->toBe(20_000_000)
            ->and($composerLine2->net_payable_minor)->toBe(10_000_000);
    }

    public function test_settlement_is_strictly_idempotent(): void
    {
        $s1 = $this->waterfallService->settleRoyalty([
            'license_id' => $this->testLicense->id,
            'billing_period' => '2026-10',
            'gross_revenue_minor' => 50_000_000,
            'distribution_fee_bps' => 1000,
            'idempotency_key' => 'STMT:IDEMP:001',
        ]);

        $s2 = $this->waterfallService->settleRoyalty([
            'license_id' => $this->testLicense->id,
            'billing_period' => '2026-10',
            'gross_revenue_minor' => 50_000_000,
            'distribution_fee_bps' => 1000,
            'idempotency_key' => 'STMT:IDEMP:001',
        ]);

        expect($s1->id)->toBe($s2->id);
    }

    public function test_settlement_without_active_license_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->waterfallService->settleRoyalty([
            'license_id' => (string) Str::uuid(), // non-existent
            'billing_period' => '2026-10',
            'gross_revenue_minor' => 50_000_000,
            'distribution_fee_bps' => 1000,
            'idempotency_key' => 'STMT:NO-LIC',
        ]);
    }

    public function test_zero_gross_revenue_produces_zero_distribution(): void
    {
        $statement = $this->waterfallService->settleRoyalty([
            'license_id' => $this->testLicense->id,
            'billing_period' => '2026-10',
            'gross_revenue_minor' => 0,
            'distribution_fee_bps' => 1000,
            'idempotency_key' => 'STMT:ZERO:01',
        ]);

        expect($statement->total_royalty_distributed_minor)->toBe(0);
    }

    public function test_balanced_ledger_entries_created_for_royalty_settlement(): void
    {
        $statement = $this->waterfallService->settleRoyalty([
            'license_id' => $this->testLicense->id,
            'billing_period' => '2026-10',
            'gross_revenue_minor' => 40_000_000,
            'distribution_fee_bps' => 1000,
            'idempotency_key' => 'STMT:LEDGER:01',
        ]);

        expect($statement->id)->not->toBeNull();
        $exitReconcile = Artisan::call('bank:reconcile');
        expect($exitReconcile)->toBe(0);
    }

    public function test_negative_gross_revenue_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->waterfallService->settleRoyalty([
            'license_id' => $this->testLicense->id,
            'billing_period' => '2026-10',
            'gross_revenue_minor' => -1000,
            'distribution_fee_bps' => 1000,
            'idempotency_key' => 'STMT:NEG:REV',
        ]);
    }

    public function test_artisan_command_med_settle_royalties_executes_successfully(): void
    {
        $exitCode = Artisan::call('med:settle-royalties', [
            '--period' => '2026-10',
        ]);

        expect($exitCode)->toBe(0);
    }
}
