<?php

declare(strict_types=1);

namespace Tests\Acceptance\Energy;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Egy\Application\Services\ConsolidatedUtilityBillingService;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Domain\Models\UtilityInvoiceLine;
use RuntimeException;
use Tests\TestCase;

class AuditAndIntegritySabotageTest extends TestCase
{
    use RefreshDatabase;

    protected ConsolidatedUtilityBillingService $billingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->billingService = app(ConsolidatedUtilityBillingService::class);
    }

    public function test_baseline_egy_audit_passes_with_zero_discrepancies(): void
    {
        $exitCode = Artisan::call('egy:audit');
        expect($exitCode)->toBe(0);
    }

    public function test_corruption_fixture_tampered_meter_reading_delta_caught_by_audit(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-SAB-01',
            'consumer_property_type' => 'FACTORY',
            'consumer_property_id' => 'PROP-SAB-01',
            'status' => 'ACTIVE',
        ]);

        $reading = MeterReading::create([
            'id' => (string) Str::uuid(),
            'meter_id' => $meter->id,
            'reading_at' => CarbonImmutable::parse('2026-10-01 10:00:00'),
            'cumulative_wh' => 100000,
            'delta_wh' => 100000,
            'interval_status' => 'OK',
            'rate_per_kwh_minor' => 1450,
            'is_wbp' => false,
        ]);

        $this->billingService->generateMonthlyInvoice('PROP-SAB-01', '2026-10');

        // Sabotage via raw DB update (bypassing Eloquent model guard)
        DB::table('egy_meter_readings')->where('id', $reading->id)->update(['delta_wh' => 999999]);

        $exitCode = Artisan::call('egy:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_corruption_fixture_deleted_invoice_line_caught_by_audit(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-SAB-02',
            'consumer_property_type' => 'MALL',
            'consumer_property_id' => 'PROP-SAB-02',
            'status' => 'ACTIVE',
        ]);

        MeterReading::create([
            'id' => (string) Str::uuid(),
            'meter_id' => $meter->id,
            'reading_at' => CarbonImmutable::parse('2026-10-01 10:00:00'),
            'cumulative_wh' => 50000,
            'delta_wh' => 50000,
            'interval_status' => 'OK',
            'rate_per_kwh_minor' => 1450,
            'is_wbp' => false,
        ]);

        $invoice = $this->billingService->generateMonthlyInvoice('PROP-SAB-02', '2026-10');

        // Sabotage: delete an invoice line directly in DB
        UtilityInvoiceLine::where('invoice_id', $invoice->id)->delete();

        $exitCode = Artisan::call('egy:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_corruption_fixture_broken_intercompany_pair_caught_by_audit(): void
    {
        // Seed an un-mirrored / broken intercompany transaction
        $tx = LedgerTransaction::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'ENERGY_PPA_NET_METER_SETTLE',
            'idempotency_key' => 'sabotage:ic:broken:01',
            'description' => 'Unmirrored IC entry',
            'posted_at' => now(),
        ]);

        $arAccount = LedgerAccount::where('code', 'ar:ic:producer:egy:IDR')->firstOrFail();

        DB::table('bank_ledger_entries')->insert([
            'transaction_id' => $tx->id,
            'account_id' => $arAccount->id,
            'asset_code' => 'IDR',
            'amount' => '-1000000.00',
            'balance_after' => '-1000000.00',
            'created_at' => now(),
        ]);

        $exitCode = Artisan::call('egy:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_corruption_fixture_tampered_ledger_posting_caught_by_audit(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-SAB-04',
            'consumer_property_type' => 'HOSPITAL',
            'consumer_property_id' => 'PROP-SAB-04',
            'status' => 'ACTIVE',
        ]);

        MeterReading::create([
            'id' => (string) Str::uuid(),
            'meter_id' => $meter->id,
            'reading_at' => CarbonImmutable::parse('2026-10-01 10:00:00'),
            'cumulative_wh' => 50000,
            'delta_wh' => 50000,
            'interval_status' => 'OK',
            'rate_per_kwh_minor' => 1450,
            'is_wbp' => false,
        ]);

        $invoice = $this->billingService->generateMonthlyInvoice('PROP-SAB-04', '2026-10');
        $this->billingService->issueInvoice($invoice->id);

        // Sabotage: corrupt the posted AR entry
        $entry = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', "ar:egy:property:{$invoice->property_id}:IDR"))->first();
        if ($entry) {
            DB::table('bank_ledger_entries')->where('id', $entry->id)->update([
                'amount' => '1.00',
            ]);
        }

        $exitCode = Artisan::call('egy:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_model_guard_rejects_reading_update(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-GUARD-01',
            'consumer_property_type' => 'OFFICE',
            'consumer_property_id' => 'PROP-G-01',
            'status' => 'ACTIVE',
        ]);

        $reading = MeterReading::create([
            'id' => (string) Str::uuid(),
            'meter_id' => $meter->id,
            'reading_at' => CarbonImmutable::now(),
            'cumulative_wh' => 10000,
            'delta_wh' => 10000,
            'interval_status' => 'OK',
            'rate_per_kwh_minor' => 1450,
            'is_wbp' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('immutable append-only');

        $reading->update(['delta_wh' => 9999]);
    }

    public function test_model_guard_rejects_reading_delete(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-GUARD-02',
            'consumer_property_type' => 'OFFICE',
            'consumer_property_id' => 'PROP-G-02',
            'status' => 'ACTIVE',
        ]);

        $reading = MeterReading::create([
            'id' => (string) Str::uuid(),
            'meter_id' => $meter->id,
            'reading_at' => CarbonImmutable::now(),
            'cumulative_wh' => 10000,
            'delta_wh' => 10000,
            'interval_status' => 'OK',
            'rate_per_kwh_minor' => 1450,
            'is_wbp' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('immutable append-only');

        $reading->delete();
    }

    public function test_two_source_audit_verifies_facts_vs_invoice_vs_ledger(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-2SRC-01',
            'consumer_property_type' => 'VENUE',
            'consumer_property_id' => 'PROP-VEN-01',
            'status' => 'ACTIVE',
        ]);

        MeterReading::create([
            'id' => (string) Str::uuid(),
            'meter_id' => $meter->id,
            'reading_at' => CarbonImmutable::parse('2026-10-01 10:00:00'),
            'cumulative_wh' => 100000,
            'delta_wh' => 100000,
            'interval_status' => 'OK',
            'rate_per_kwh_minor' => 1450,
            'is_wbp' => false,
        ]);

        $invoice = $this->billingService->generateMonthlyInvoice('PROP-VEN-01', '2026-10');
        $this->billingService->issueInvoice($invoice->id);

        $exitCode = Artisan::call('egy:audit');
        expect($exitCode)->toBe(0);
    }
}
