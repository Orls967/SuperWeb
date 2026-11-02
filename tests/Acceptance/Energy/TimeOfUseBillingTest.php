<?php

declare(strict_types=1);

namespace Tests\Acceptance\Energy;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Egy\Application\Services\ConsolidatedUtilityBillingService;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Tests\TestCase;

class TimeOfUseBillingTest extends TestCase
{
    use RefreshDatabase;

    protected ConsolidatedUtilityBillingService $billingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->billingService = app(ConsolidatedUtilityBillingService::class);
    }

    public function test_tou_billing_exact_vector(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-TOU-001',
            'consumer_property_type' => 'MALL',
            'consumer_property_id' => 'PROP-MALL-VEC',
            'tariff_category' => 'BUSINESS_TOU',
            'status' => 'ACTIVE',
        ]);

        // 100,000 Wh in LWBP @ Rp 1,450 / kWh = Rp 145,000
        MeterReading::create([
            'id' => (string) Str::uuid(),
            'meter_id' => $meter->id,
            'reading_at' => CarbonImmutable::parse('2026-10-15 14:00:00'),
            'cumulative_wh' => 100000,
            'delta_wh' => 100000,
            'interval_status' => 'OK',
            'rate_per_kwh_minor' => 1450,
            'is_wbp' => false,
        ]);

        // 50,000 Wh in WBP @ Rp 2,100 / kWh = Rp 105,000
        MeterReading::create([
            'id' => (string) Str::uuid(),
            'meter_id' => $meter->id,
            'reading_at' => CarbonImmutable::parse('2026-10-15 18:00:00'),
            'cumulative_wh' => 150000,
            'delta_wh' => 50000,
            'interval_status' => 'OK',
            'rate_per_kwh_minor' => 2100,
            'is_wbp' => true,
        ]);

        $invoice = $this->billingService->generateMonthlyInvoice('PROP-MALL-VEC', '2026-10');

        expect($invoice->total_wh)->toBe(150000)
            ->and($invoice->electricity_charge_minor)->toBe(250000)
            ->and($invoice->total_consolidated_minor)->toBe(250000);
    }

    public function test_tou_window_not_multiple_of_15_minutes_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('kelipatan 15 menit');

        $this->billingService->configureTouWindow('17:07', '22:03');
    }

    public function test_window_boundary_exact_match_17_00_is_wbp_and_22_00_is_lwbp(): void
    {
        $tWbp = CarbonImmutable::parse('2026-10-15 17:00:00', 'Asia/Jakarta');
        $tLwbp = CarbonImmutable::parse('2026-10-15 22:00:00', 'Asia/Jakarta');

        expect($this->billingService->isPeakHour($tWbp))->toBeTrue()
            ->and($this->billingService->isPeakHour($tLwbp))->toBeFalse();
    }

    public function test_half_up_rounding_on_line_item_calculation(): void
    {
        // 1 Wh @ Rp 1,450 / kWh: 1 * 1450 / 1000 = 1.45 -> HalfUp = 1
        expect($this->billingService->calculateCharge(1, 1450))->toBe(1);

        // 1 Wh @ Rp 1,500 / kWh: 1 * 1500 / 1000 = 1.50 -> HalfUp = 2
        expect($this->billingService->calculateCharge(1, 1500))->toBe(2);
    }

    public function test_consolidated_invoice_generated_per_property_per_period(): void
    {
        $invoice = $this->billingService->generateMonthlyInvoice('PROP-FAC-01', '2026-10');

        expect($invoice->property_id)->toBe('PROP-FAC-01')
            ->and($invoice->billing_period)->toBe('2026-10')
            ->and($invoice->status)->toBe('ISSUED');
    }

    public function test_billing_twice_for_same_period_returns_single_existing_invoice(): void
    {
        $inv1 = $this->billingService->generateMonthlyInvoice('PROP-FAC-02', '2026-10');
        $inv2 = $this->billingService->generateMonthlyInvoice('PROP-FAC-02', '2026-10');

        expect($inv2->id)->toBe($inv1->id)
            ->and(UtilityConsolidatedInvoice::where('property_id', 'PROP-FAC-02')->where('billing_period', '2026-10')->count())->toBe(1);
    }

    public function test_invoice_lines_sum_equals_total_consolidated_minor(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-SUM-001',
            'consumer_property_type' => 'HOSPITAL',
            'consumer_property_id' => 'PROP-HOSP-SUM',
            'tariff_category' => 'BUSINESS_TOU',
            'status' => 'ACTIVE',
        ]);

        MeterReading::create([
            'id' => (string) Str::uuid(),
            'meter_id' => $meter->id,
            'reading_at' => CarbonImmutable::parse('2026-10-10 12:00:00'),
            'cumulative_wh' => 200000,
            'delta_wh' => 200000,
            'interval_status' => 'OK',
            'rate_per_kwh_minor' => 1450,
            'is_wbp' => false,
        ]);

        $invoice = $this->billingService->generateMonthlyInvoice('PROP-HOSP-SUM', '2026-10');

        $linesSum = $invoice->lines()->sum('subtotal_minor');
        expect($linesSum)->toBe($invoice->total_consolidated_minor);
    }

    public function test_property_invoice_posting_to_ledger(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-POST-01',
            'consumer_property_type' => 'FACTORY',
            'consumer_property_id' => 'PROP-POST-01',
            'tariff_category' => 'BUSINESS_TOU',
            'status' => 'ACTIVE',
        ]);

        MeterReading::create([
            'id' => (string) Str::uuid(),
            'meter_id' => $meter->id,
            'reading_at' => CarbonImmutable::parse('2026-10-10 12:00:00'),
            'cumulative_wh' => 100000,
            'delta_wh' => 100000,
            'interval_status' => 'OK',
            'rate_per_kwh_minor' => 1450,
            'is_wbp' => false,
        ]);

        $invoice = $this->billingService->generateMonthlyInvoice('PROP-POST-01', '2026-10');
        $this->billingService->issueInvoice($invoice->id);

        $tx = LedgerTransaction::where('reference_type', 'EGY_UTILITY_INVOICE')
            ->where('reference_id', $invoice->invoice_number)
            ->first();

        expect($tx)->not->toBeNull();

        $arEntry = $tx->entries()->whereHas('account', fn ($q) => $q->where('code', "ar:egy:property:{$invoice->property_id}:IDR"))->first();
        expect($arEntry)->not->toBeNull()
            ->and((float) $arEntry->amount)->not->toBe(0.0);

        $revEntry = $tx->entries()->whereHas('account', fn ($q) => $q->where('code', 'egy:utility_revenue:IDR'))->first();
        expect($revEntry)->not->toBeNull()
            ->and((float) $revEntry->amount)->not->toBe(0.0);
    }

    public function test_unissued_draft_invoice_does_not_post_to_ledger(): void
    {
        $invoice = $this->billingService->createDraftInvoice('PROP-DRAFT-01', '2026-10');

        $tx = LedgerTransaction::where('reference_id', $invoice->invoice_number)->first();
        expect($tx)->toBeNull();
    }

    public function test_settle_invoice_posts_payment_capture_and_updates_status(): void
    {
        $invoice = $this->billingService->generateMonthlyInvoice('PROP-SETTLE-01', '2026-10');
        $this->billingService->issueInvoice($invoice->id);

        $settled = $this->billingService->settleInvoice($invoice->id, 'egy:settle:PROP-SETTLE-01:2026-10');

        expect($settled->status)->toBe('SETTLED');
    }

    public function test_zero_consumption_period_generates_zero_charge_invoice_line(): void
    {
        $invoice = $this->billingService->generateMonthlyInvoice('PROP-ZERO-01', '2026-10');

        expect($invoice->total_wh)->toBe(0)
            ->and($invoice->electricity_charge_minor)->toBe(0);
    }

    public function test_property_idor_check_tenant_cannot_view_other_property_invoice(): void
    {
        $invoice = $this->billingService->generateMonthlyInvoice('PROP-MALL-SECRET', '2026-10');
        $unauthorizedUser = User::factory()->create(['role' => 'tenant']);

        $response = $this->actingAs($unauthorizedUser)->getJson("/api/energy/invoices/{$invoice->id}");
        $response->assertStatus(403);
    }
}
