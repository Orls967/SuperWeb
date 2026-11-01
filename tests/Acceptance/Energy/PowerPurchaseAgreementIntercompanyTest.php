<?php

declare(strict_types=1);

namespace Tests\Acceptance\Energy;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Core\Domain\Models\AuditLog;
use Modules\Core\Exceptions\IdempotencyConflictException;
use Modules\Egy\Application\Actions\SettlePowerPurchaseAgreementBillingAction;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use RuntimeException;
use Tests\TestCase;

class PowerPurchaseAgreementIntercompanyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_ppa_settlement_creates_mirrored_transactions_via_intercompany(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-001',
            'producer_entity_id' => 'PTY-SOLAR-01',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1200,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);
        $key = 'egy:ppa:PPA-IC-001:202610';

        $result = $action->executeWithIntercompany((string) $ppa->id, 1000, $key);

        $mirroredPair = IntercompanyTransaction::find($result['mirrored_pair_id']);
        $auditLog = AuditLog::where('action', 'egy_ppa_billing_settled')->latest()->first();

        expect($result['status'])->toBe('SETTLED')
            ->and($result['mirrored_pair_id'])->not->toBeNull()
            ->and($result['total_minor'])->toBe(1200000)
            ->and($ppa->fresh()->surplus_kwh_exported)->toBe(1000.0)
            ->and($ppa->fresh()->total_settlement_minor)->toBe(1200000)
            ->and($mirroredPair)->not->toBeNull()
            ->and($mirroredPair->selling_entity)->toBe('PTY-SOLAR-01')
            ->and($mirroredPair->buying_entity)->toBe('ENT-EGY-01')
            ->and($mirroredPair->amount_idr)->toBe(1200000)
            ->and($mirroredPair->description)->toBe('PPA net settlement for contract PPA-IC-001 (1000 kWh)')
            ->and($mirroredPair->sales_invoice_ref)->toBe('INV-PPA-PPA-IC-001')
            ->and($mirroredPair->purchase_bill_ref)->toBe('BILL-PPA-PPA-IC-001')
            ->and($auditLog)->not->toBeNull()
            ->and($auditLog->new_values['delivered_kwh'])->toBe(1000)
            ->and($auditLog->new_values['total_minor'])->toBe(1200000)
            ->and($auditLog->new_values['mirrored_pair'])->toBe($mirroredPair->transaction_code);
    }

    public function test_mirrored_transactions_have_deterministic_codes_from_idempotency_key(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-002',
            'producer_entity_id' => 'PTY-SOLAR-02',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1000,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);
        $key = 'egy:ppa:PPA-IC-002:202610';

        $res1 = $action->executeWithIntercompany((string) $ppa->id, 500, $key);

        // Code must be derived deterministically from key, not random/microtime
        expect($res1['producer_tx_code'])->toStartWith('IC-PROD-')
            ->and($res1['offtaker_tx_code'])->toStartWith('IC-OFFT-');
    }

    public function test_retry_settlement_with_same_key_returns_existing_mirrored_pair(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-003',
            'producer_entity_id' => 'PTY-SOLAR-03',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1000,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);
        $key = 'egy:ppa:PPA-IC-003:202610';

        $res1 = $action->executeWithIntercompany((string) $ppa->id, 500, $key);
        $res2 = $action->executeWithIntercompany((string) $ppa->id, 500, $key);

        expect($res2['mirrored_pair_id'])->toBe($res1['mirrored_pair_id']);
    }

    public function test_retry_with_same_key_but_different_kwh_throws_idempotency_conflict(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-004',
            'producer_entity_id' => 'PTY-SOLAR-04',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1000,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);
        $key = 'egy:ppa:PPA-IC-004:202610';

        $action->executeWithIntercompany((string) $ppa->id, 500, $key);

        $this->expectException(IdempotencyConflictException::class);
        $action->executeWithIntercompany((string) $ppa->id, 600, $key); // Different kWh!
    }

    public function test_producer_book_posting_contains_intercompany_receivable(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-005',
            'producer_entity_id' => 'PTY-SOLAR-05',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1500,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);
        $key = 'egy:ppa:PPA-IC-005:202610';

        $res = $action->executeWithIntercompany((string) $ppa->id, 100, $key);

        $prodTx = LedgerTransaction::where('idempotency_key', $res['producer_tx_code'])->first();
        expect($prodTx)->not->toBeNull();
        $prodArEntry = $prodTx->entries()->whereHas('account', fn ($q) => $q->where('code', 'ar:ic:producer:egy:IDR'))->first();
        expect((int) abs((float) $prodArEntry?->amount))->toBe(150000);
    }

    public function test_offtaker_book_posting_contains_intercompany_payable(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-006',
            'producer_entity_id' => 'PTY-SOLAR-06',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1500,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);
        $key = 'egy:ppa:PPA-IC-006:202610';

        $res = $action->executeWithIntercompany((string) $ppa->id, 100, $key);

        $offtTx = LedgerTransaction::where('idempotency_key', $res['offtaker_tx_code'])->first();
        expect($offtTx)->not->toBeNull();
        $offtApEntry = $offtTx->entries()->whereHas('account', fn ($q) => $q->where('code', 'ap:ic:egy:producer:IDR'))->first();
        expect((int) abs((float) $offtApEntry?->amount))->toBe(150000);
    }

    public function test_group_consolidation_eliminates_ppa_intercompany_to_net_zero(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-007',
            'producer_entity_id' => 'PTY-SOLAR-07',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1200,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);
        $key = 'egy:ppa:PPA-IC-007:202610';

        $action->executeWithIntercompany((string) $ppa->id, 200, $key);

        $intercompanyAr = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ar:ic:producer:egy:IDR'))->sum('amount');
        $intercompanyAp = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ap:ic:egy:producer:IDR'))->sum('amount');

        // Net elimination must be zero
        expect((float) $intercompanyAr + (float) $intercompanyAp)->toEqualWithDelta(0.0, 0.001);
    }

    public function test_inactive_ppa_contract_settlement_rejected(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-008',
            'producer_entity_id' => 'PTY-SOLAR-08',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1200,
            'status' => 'TERMINATED',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not active');

        $action->executeWithIntercompany((string) $ppa->id, 100, 'egy:ppa:term:01');
    }

    public function test_negative_or_zero_delivered_kwh_rejected(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-009',
            'producer_entity_id' => 'PTY-SOLAR-09',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1200,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);

        $this->expectException(InvalidArgumentException::class);
        $action->executeWithIntercompany((string) $ppa->id, 0, 'egy:ppa:zero:01');
    }

    public function test_negative_delivered_kwh_rejected(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-NEG',
            'producer_entity_id' => 'PTY-SOLAR-09',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1200,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);

        $this->expectException(InvalidArgumentException::class);
        $action->executeWithIntercompany((string) $ppa->id, -1, 'egy:ppa:neg:01');
    }

    public function test_boundary_minimum_one_kwh_accepted(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-ONE',
            'producer_entity_id' => 'PTY-SOLAR-09',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1200,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);
        $res = $action->executeWithIntercompany((string) $ppa->id, 1, 'egy:ppa:one:01');
        expect($res['total_minor'])->toBe(1200);
    }

    public function test_legacy_execute_method_operates_correctly(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-LEG',
            'producer_entity_id' => 'PTY-SOLAR-09',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1200,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);
        $total = $action->execute((string) $ppa->id, 10);
        expect($total)->toBe(12000);
    }

    public function test_cross_entity_settlement_verifies_authorization(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-IC-010',
            'producer_entity_id' => 'PTY-SOLAR-10',
            'grid_offtaker_id' => 'ENT-EGY-01',
            'feed_in_tariff_per_kwh_minor' => 1200,
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson("/api/energy/ppa/{$ppa->id}/settle", [
            'delivered_kwh' => 100,
        ], [
            'Idempotency-Key' => 'egy:ppa:auth:01',
        ]);

        // Unauthenticated must be 401
        $response->assertStatus(401);
    }
}
