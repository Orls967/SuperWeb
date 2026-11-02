<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Mining\Application\Actions\EvaluateContractorFuelAnomalyAction;
use Modules\Mining\Domain\Models\MiningContractorEvaluation;
use Modules\Mining\Domain\Models\MiningSite;
use Tests\TestCase;

class MiningContractorPenaltyAndFuelTest extends TestCase
{
    use RefreshDatabase;

    protected MiningSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SUL-01',
            'name' => 'Pomalaa Nickel Site',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Kolaka',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_evaluasi_konsumsi_bbm_menggunakan_robust_statistics_median_dan_mad_t8(): void
    {
        // 10 baseline samples of SFC (Specific Fuel Consumption per 100 ton-km):
        // [30, 31, 32, 31, 30, 33, 32, 31, 30, 32]
        // Median = 31, MAD is small.
        // Contractor truck consumed 55 liters per 100 ton-km -> strong anomaly!
        $action = app(EvaluateContractorFuelAnomalyAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            contractorPartyId: 'CONT-PT-001',
            period: '2026-10',
            totalTonKm: 50000,
            fuelConsumedLiters: 27500, // 55 L per 100 ton-km
            historicalSfcSamples: [30, 31, 32, 31, 30, 33, 32, 31, 30, 32],
            penaltyAmountIdr: 25000000,
            idempotencyKey: 'min:pen:fuel:001'
        );

        $this->assertTrue($res->isAnomaly);
        $this->assertTrue($res->paymentHeld);
        $this->assertSame(31, (int) round($res->baselineMedian));
    }

    public function test_konsumsi_wajar_tidak_ditandai_anomali(): void
    {
        $action = app(EvaluateContractorFuelAnomalyAction::class);

        // SFC = 31.5 L per 100 ton-km (near median 31)
        $res = $action->handle(
            siteId: $this->site->id,
            contractorPartyId: 'CONT-PT-001',
            period: '2026-10',
            totalTonKm: 50000,
            fuelConsumedLiters: 15750, // 31.5 L per 100 ton-km
            historicalSfcSamples: [30, 31, 32, 31, 30, 33, 32, 31, 30, 32],
            penaltyAmountIdr: 25000000,
            idempotencyKey: 'min:pen:fuel:002'
        );

        $this->assertFalse($res->isAnomaly);
        $this->assertFalse($res->paymentHeld);
    }

    public function test_anomali_bbm_membekukan_pembayaran_kontraktor_dan_mencatat_evaluasi(): void
    {
        $action = app(EvaluateContractorFuelAnomalyAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            contractorPartyId: 'CONT-PT-002',
            period: '2026-10',
            totalTonKm: 20000,
            fuelConsumedLiters: 14000, // 70 L per 100 ton-km -> massive fuel theft anomaly!
            historicalSfcSamples: [30, 31, 32, 31, 30, 33, 32, 31, 30, 32],
            penaltyAmountIdr: 50000000,
            idempotencyKey: 'min:pen:fuel:003'
        );

        $this->assertDatabaseHas('min_contractor_evaluations', [
            'id' => $res->evaluation->id,
            'contractor_party_id' => 'CONT-PT-002',
            'is_anomaly' => true,
            'payment_held' => true,
            'penalty_amount_idr' => 50000000,
        ]);
    }

    public function test_penerbitan_penalti_kontraktor_memposting_piutang_dan_pendapatan_denda_di_ledger(): void
    {
        $action = app(EvaluateContractorFuelAnomalyAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            contractorPartyId: 'CONT-PT-003',
            period: '2026-10',
            totalTonKm: 20000,
            fuelConsumedLiters: 14000,
            historicalSfcSamples: [30, 31, 32, 31, 30, 33, 32, 31, 30, 32],
            penaltyAmountIdr: 15000000,
            idempotencyKey: 'min:pen:fuel:004'
        );

        // Debit: ar:min:contractor:CONT-PT-003:IDR (-15000000)
        $entryDebit = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ar:min:contractor:CONT-PT-003:IDR'))->first();
        $this->assertNotNull($entryDebit);
        $this->assertSame(-15000000, (int) $entryDebit->amount);

        // Credit: min:penalty_income:IDR (+15000000)
        $entryCredit = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'min:penalty_income:IDR'))->first();
        $this->assertNotNull($entryCredit);
        $this->assertSame(15000000, (int) $entryCredit->amount);
    }

    public function test_evaluasi_dan_penalti_dua_kali_dengan_key_sama_bersifat_idempoten(): void
    {
        $action = app(EvaluateContractorFuelAnomalyAction::class);

        $res1 = $action->handle(
            siteId: $this->site->id,
            contractorPartyId: 'CONT-PT-004',
            period: '2026-10',
            totalTonKm: 20000,
            fuelConsumedLiters: 14000,
            historicalSfcSamples: [30, 31, 32, 31, 30, 33, 32, 31, 30, 32],
            penaltyAmountIdr: 10000000,
            idempotencyKey: 'min:pen:fuel:005'
        );

        $res2 = $action->handle(
            siteId: $this->site->id,
            contractorPartyId: 'CONT-PT-004',
            period: '2026-10',
            totalTonKm: 20000,
            fuelConsumedLiters: 14000,
            historicalSfcSamples: [30, 31, 32, 31, 30, 33, 32, 31, 30, 32],
            penaltyAmountIdr: 10000000,
            idempotencyKey: 'min:pen:fuel:005'
        );

        $this->assertSame($res1->evaluation->id, $res2->evaluation->id);
        $this->assertSame(1, MiningContractorEvaluation::where('contractor_party_id', 'CONT-PT-004')->where('period', '2026-10')->count());
    }

    public function test_invarian_buku_besar_penalti_kontraktor_seimbang_nol(): void
    {
        $action = app(EvaluateContractorFuelAnomalyAction::class);

        $action->handle(
            siteId: $this->site->id,
            contractorPartyId: 'CONT-PT-005',
            period: '2026-10',
            totalTonKm: 20000,
            fuelConsumedLiters: 14000,
            historicalSfcSamples: [30, 31, 32, 31, 30, 33, 32, 31, 30, 32],
            penaltyAmountIdr: 30000000,
            idempotencyKey: 'min:pen:fuel:006'
        );

        $totalDebits = abs((int) LedgerEntry::where('amount', '<', 0)->sum('amount'));
        $totalCredits = (int) LedgerEntry::where('amount', '>', 0)->sum('amount');

        $this->assertGreaterThan(0, $totalDebits);
        $this->assertSame($totalDebits, $totalCredits);
        $this->assertSame(0, (int) LedgerEntry::sum('amount'));
    }
}
