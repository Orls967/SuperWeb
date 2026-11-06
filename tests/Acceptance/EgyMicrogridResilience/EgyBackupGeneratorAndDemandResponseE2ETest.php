<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyMicrogridResilience;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Egy\Application\Actions\ProcessDemandResponseCurtailmentAction;
use Modules\Egy\Application\Actions\RecordGeneratorTestRunAction;
use Tests\TestCase;

final class EgyBackupGeneratorAndDemandResponseE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_generator_fuel_scope_1_and_demand_response_incentive(): void
    {
        $this->seed();

        $genAction = app(RecordGeneratorTestRunAction::class);
        $drAction = app(ProcessDemandResponseCurtailmentAction::class);

        // 1. Pengujian Beban Mingguan Genset
        // Konsumsi 100 Liter solar selama 60 menit dengan beban 50%
        // Emisi Scope 1 = (100 * 2680) / 1000 = 268 kg CO2e
        $log = $genAction->execute(
            generatorAssetCode: 'GENSET-TEST-CUMMINS',
            siteName: 'Genset Standby Mall',
            runType: 'weekly_load_test',
            startTime: Carbon::parse('2026-11-07 10:00:00'),
            durationMinutes: 60,
            fuelConsumedLiters: 100,
            loadPercentageBps: 5000,
            fuelPriceIdrPerLiter: 15000,
            notes: 'Uji beban mingguan normal'
        );

        $this->assertTrue($log->is_passed);
        $this->assertSame(100, $log->fuel_consumed_liters);
        $this->assertSame(268, $log->emissions_kg_co2e);

        // Verifikasi pembukuan biaya bahan bakar (100 L * Rp 15.000 = Rp 1.500.000)
        $fuelEntry = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'egy:genset_fuel_expense:IDR'))
            ->latest('id')
            ->first();
        $this->assertNotNull($fuelEntry);
        $this->assertSame(1_500_000, (int) abs((float) $fuelEntry->amount));

        // 2. Partisipasi Demand-Response PLN
        // Target curtailment 400 kW, tercapai 380 kW (> 80%), waktu respons 360 detik (6 menit < 15 menit)
        // Insentif = 380 kW * Rp 25.000 = Rp 9.500.000
        $drEvent = $drAction->execute(
            eventCode: 'DR-TEST-PLN-01',
            curtailmentSignalId: 'SIG-PLN-9988',
            startTime: Carbon::parse('2026-11-07 18:30:00'),
            targetCurtailmentKw: 400,
            achievedCurtailmentKw: 380,
            responseTimeSeconds: 360,
            incentiveRateIdrPerKw: 25000,
            idempotencyKey: 'IDEMP-DR-TEST-01'
        );

        $this->assertSame('completed', $drEvent->status);
        $this->assertSame(9_500_000, $drEvent->total_reward_idr);

        // Verifikasi piutang ke operator grid PLN di ledger
        $drEntry = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ar:egy:customer:PLN:IDR'))
            ->latest('id')
            ->first();
        $this->assertNotNull($drEntry);
        $this->assertSame(9_500_000, (int) abs((float) $drEntry->amount));
    }
}
