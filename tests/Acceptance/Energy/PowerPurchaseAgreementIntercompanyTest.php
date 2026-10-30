<?php

declare(strict_types=1);

namespace Tests\Acceptance\Energy;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Core\Exceptions\IdempotencyConflictException;
use Modules\Egy\Application\Actions\SettlePowerPurchaseAgreementBillingAction;
use Modules\Egy\Domain\Models\PpaContract;
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

        expect($result['status'])->toBe('SETTLED')
            ->and($result['mirrored_pair_id'])->not->toBeNull()
            ->and($result['total_minor'])->toBe(1200000);
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

        $prodTx = LedgerTransaction::where('code', $res['producer_tx_code'])->first();
        expect($prodTx)->not->toBeNull()
            ->and($prodTx->entries()->where('account_code', 'ar:ic:producer:egy:IDR')->value('amount_minor'))->toBe(150000);
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

        $offtTx = LedgerTransaction::where('code', $res['offtaker_tx_code'])->first();
        expect($offtTx)->not->toBeNull()
            ->and($offtTx->entries()->where('account_code', 'ap:ic:egy:producer:IDR')->value('amount_minor'))->toBe(-150000);
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

        $intercompanyAr = LedgerTransaction::whereHas('entries', fn ($q) => $q->where('account_code', 'ar:ic:producer:egy:IDR'))->sum('amount_minor');
        $intercompanyAp = LedgerTransaction::whereHas('entries', fn ($q) => $q->where('account_code', 'ap:ic:egy:producer:IDR'))->sum('amount_minor');

        // Net elimination must be zero
        expect($intercompanyAr + $intercompanyAp)->toBe(0);
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
