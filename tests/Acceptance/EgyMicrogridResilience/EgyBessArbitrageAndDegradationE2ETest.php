<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyMicrogridResilience;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Egy\Application\Actions\ExecuteBessArbitrageAction;
use Modules\Egy\Domain\Models\BessAsset;
use Tests\TestCase;

final class EgyBessArbitrageAndDegradationE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_bess_arbitrage_positive_margin_and_degradation_ledger_posting(): void
    {
        $this->seed();

        $action = app(ExecuteBessArbitrageAction::class);

        $bess = BessAsset::create([
            'bess_code' => 'BESS-TEST-01',
            'site_name' => 'Kawasan Baterai Uji',
            'battery_chemistry' => 'LFP',
            'capacity_kwh' => 1000,
            'max_charge_power_kw' => 250,
            'max_discharge_power_kw' => 250,
            'current_soc_bps' => 2000,
            'state_of_health_bps' => 10000,
            'cycle_count' => 10,
            'replacement_cost_idr' => 2_500_000_000,
            'status' => 'operational',
        ]);

        $tariffProfile = [
            900, 900, 900, 900, 900, 900,
            1450, 1450, 1450, 1450, 1450, 1450, 1450, 1450, 1450, 1450, 1450,
            2200, 2200, 2200, 2200, 2200, 2200,
            900,
        ];

        $dispatch = $action->execute(
            bessAssetId: $bess->id,
            hourlyTariffProfile: $tariffProfile,
            dispatchDate: '2026-11-07',
            idempotencyKey: 'IDEMP-BESS-TEST-01'
        );

        $this->assertSame('arbitrage', $dispatch->dispatch_type);
        $this->assertGreaterThan(0, $dispatch->energy_kwh);
        $this->assertGreaterThan(0, $dispatch->discharging_revenue_idr);
        $this->assertGreaterThan(0, $dispatch->degradation_cost_idr);
        // Net profit harus positif setelah dikurangi biaya energi dan degradasi baterai
        $this->assertGreaterThan(0, $dispatch->net_profit_idr);
        $this->assertSame(
            $dispatch->net_profit_idr,
            $dispatch->discharging_revenue_idr - ($dispatch->charging_cost_idr + $dispatch->degradation_cost_idr)
        );
        $this->assertNotNull($dispatch->ledger_transaction_id);

        // Verifikasi alokasi cadangan degradasi baterai di sisi kredit ledger (LIABILITY)
        $reserveEntry = LedgerEntry::where('transaction_id', $dispatch->ledger_transaction_id)
            ->whereHas('account', fn ($q) => $q->where('code', 'egy:bess_replacement_reserve:IDR'))
            ->first();

        $this->assertNotNull($reserveEntry);
        $this->assertSame($dispatch->degradation_cost_idr, (int) abs((float) $reserveEntry->amount));

        // Verifikasi peningkatan jumlah siklus pada baterai
        $bess->refresh();
        $this->assertSame(11, $bess->cycle_count);
    }
}
