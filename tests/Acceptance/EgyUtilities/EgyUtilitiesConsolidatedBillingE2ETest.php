<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyUtilities;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Egy\Application\Actions\ConsolidateMultiUtilityBillsAction;
use Modules\Egy\database\seeders\EgyRbacSeeder;
use Modules\Egy\Domain\Models\EgyParameter;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Tests\TestCase;

final class EgyUtilitiesConsolidatedBillingE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_consolidated_multi_utility_billing_posting_and_idempotency(): void
    {
        $this->seed();
        (new EgyRbacSeeder)->run();

        $user = User::factory()->create();
        $user->assignRole('energy_billing');

        // Parameter tarif air bertingkat: 0-10 @ 5.000, 11-20 @ 7.500, >20 @ 10.000 (S1)
        EgyParameter::create([
            'parameter_key' => 'WATER_TARIFF_TIERS',
            'version' => 1,
            'status' => 'active',
            'effective_from' => '2026-01-01',
            'value' => '{"tier1": 5000}',
            'unit' => 'IDR/m3',
            'value_payload' => [
                'tier1_max_m3' => 10, 'tier1_rate_idr' => 5000,
                'tier2_max_m3' => 20, 'tier2_rate_idr' => 7500,
                'tier3_rate_idr' => 10000,
            ],
            'label' => 'S1',
        ]);

        // Parameter tarif pendingin termal: Rp 1.200 per kWh_th
        EgyParameter::create([
            'parameter_key' => 'DISTRICT_COOLING_TARIFF',
            'version' => 1,
            'status' => 'active',
            'effective_from' => '2026-01-01',
            'value' => '{"rate": 1200}',
            'unit' => 'IDR/kWh_th',
            'value_payload' => ['rate_idr_per_kwh_th' => 1200],
            'label' => 'S1',
        ]);

        // 1. Setup meter air dan pendingin untuk property #101
        SmartMeter::create([
            'meter_serial' => 'MTR-WTR-101',
            'property_id' => 101,
            'utility_type' => 'water_potable',
            'meter_type' => 'water',
            'status' => 'active',
            'installed_at' => '2026-01-01',
            'multiplier' => 1.0,
            'interval_minutes' => 15,
        ]);

        SmartMeter::create([
            'meter_serial' => 'MTR-CLG-101',
            'property_id' => 101,
            'utility_type' => 'district_cooling_thermal',
            'meter_type' => 'thermal',
            'status' => 'active',
            'installed_at' => '2026-01-01',
            'multiplier' => 1.0,
            'interval_minutes' => 15,
        ]);

        $action = app(ConsolidateMultiUtilityBillsAction::class);

        // Eksekusi konsolidasi tagihan multi-utilitas (27 m3 air + 500 kWh_th pendingin)
        // Air 27 m3: (10 * 5000) + (10 * 7500) + (7 * 10000) = 50.000 + 75.000 + 70.000 = 195.000 Rp (sesuai vektor E5)
        // Pendingin 500 kWh_th * 1200 = 600.000 Rp
        // Total konsolidasi = 795.000 Rp
        $idempotencyKey = 'EGY:CONS:2026-11:PROP-101';
        $invoice1 = $action->execute(
            propertyId: 101,
            periodMonth: '2026-11',
            customerPartyId: 201,
            waterVolumeM3: 27,
            coolingKwhTh: 500,
            idempotencyKey: $idempotencyKey
        );

        $this->assertSame(795_000, $invoice1->total_amount_idr);
        $this->assertSame('issued', $invoice1->status);

        // 2. Verifikasi Idempotensi: pemanggilan kedua dengan key yang sama tidak membuat invoice baru
        $invoice2 = $action->execute(
            propertyId: 101,
            periodMonth: '2026-11',
            customerPartyId: 201,
            waterVolumeM3: 27,
            coolingKwhTh: 500,
            idempotencyKey: $idempotencyKey
        );

        $this->assertSame($invoice1->id, $invoice2->id);
        $this->assertSame(1, UtilityConsolidatedInvoice::where('billing_period', '2026-11')->where('property_id', 101)->count());

        // 3. Verifikasi posting ledger presisi
        $tx = LedgerTransaction::findOrFail((int) $invoice1->ledger_transaction_id);
        $entries = LedgerEntry::where('transaction_id', $tx->id)->get();
        $this->assertCount(4, $entries);

        // AR Customer debit: 195.000 + 600.000 = 795.000
        $arDebit = (int) $entries->filter(fn ($e) => str_starts_with($e->account->code, 'ar:egy:customer:201:IDR'))->sum(fn ($e) => abs((float) $e->amount));
        $this->assertEquals(795_000, $arDebit);
    }
}
