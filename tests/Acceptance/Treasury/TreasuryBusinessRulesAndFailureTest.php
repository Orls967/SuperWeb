<?php

declare(strict_types=1);

namespace Tests\Acceptance\Treasury;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Treasury\Application\Actions\DrawFacilityAction;
use Modules\Treasury\Application\Actions\ExecuteCashPoolSweepAction;
use Modules\Treasury\Application\Actions\ReverseRevaluationsAction;
use Modules\Treasury\Application\Services\TreasuryService;
use Modules\Treasury\Domain\Algorithms\RationalRate;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Exceptions\CovenantBreachException;
use Modules\Treasury\Domain\Exceptions\HedgeInvalidTransitionException;
use Modules\Treasury\Domain\Exceptions\HedgeMakerCheckerViolationException;
use Modules\Treasury\Domain\Exceptions\PoolSweepNegativeBalanceException;
use Modules\Treasury\Domain\StateMachines\HedgeContractStateMachine;
use Tests\TestCase;

class TreasuryBusinessRulesAndFailureTest extends TestCase
{
    use RefreshDatabase;

    protected TreasuryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(TreasuryService::class);
    }

    public function test_business_rules_and_failure_guards(): void
    {
        $dealer = User::factory()->create(['name' => 'Dealer', 'role' => 'treasury']);

        // 1. Guard Kurs Nol / Negatif
        try {
            new RationalRate(0, 1);
            $this->fail('Expected exception for zero numerator');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('INVALID_RATE', $e->getMessage());
        }

        try {
            new RationalRate(15000, -1);
            $this->fail('Expected exception for negative denominator');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('INVALID_RATE', $e->getMessage());
        }

        // 2. Guard Covenant DER Penarikan Fasilitas Kredit
        $facility = $this->service->createCreditFacility(
            code: 'FAC-GUARD-01',
            bankName: 'Bank Mandiri',
            limitIdr: 5_000_000_000,
            interestPercent: 8.5,
            maxDebtEquity: 2.5 // 2.50x = 2500 permil
        );
        $drawAction = app(DrawFacilityAction::class);

        $this->expectException(CovenantBreachException::class);
        $drawAction->execute(
            facility: $facility,
            amountIdr: 2_000_000_000,
            currentDerPermille: 2650, // 2.65x > 2.50x -> Melanggar!
            idempotencyKey: 'IDEM:TRS:FAIL_DRAW_01'
        );
    }

    public function test_credit_limit_exceeded_guard(): void
    {
        $facility = $this->service->createCreditFacility(
            code: 'FAC-LIMIT-01',
            bankName: 'BCA',
            limitIdr: 1_000_000_000,
            interestPercent: 8.5,
            maxDebtEquity: 2.5
        );
        $drawAction = app(DrawFacilityAction::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Penarikan melampaui plafon kredit');
        $drawAction->execute(
            facility: $facility,
            amountIdr: 1_500_000_000, // Lebih besar dari plafon 1 Miliar
            currentDerPermille: 1500,
            idempotencyKey: 'IDEM:TRS:FAIL_LIMIT_01'
        );
    }

    public function test_pool_sweep_negative_balance_guard(): void
    {
        $headerAcc = $this->service->createBankAccount('ACC-HDR-FAIL', 'Holding Header', 'IDR', 50_000_000);
        $subAcc = $this->service->createBankAccount('ACC-SUB-FAIL', 'Subsid Operasional', 'IDR', 20_000_000);

        // Target 50m, saldo hanya 20m. Sweep tidak boleh membuat saldo anak minus!
        $pool = $this->service->setupCashPool(
            poolName: 'Deficit Pool',
            headerAccountId: $headerAcc->id,
            subAccountId: $subAcc->id,
            targetBalanceIdr: 50_000_000
        );

        $sweepAction = app(ExecuteCashPoolSweepAction::class);
        // Sweep zero-balancing normal saat saldo <= target menghasilkan 0 (tidak menyapu)
        $swept = $sweepAction->execute($pool);
        $this->assertSame(0, $swept);
        $this->assertSame(20_000_000, $subAcc->fresh()->balance);
    }

    public function test_hedge_invalid_transition_guard(): void
    {
        $contract = $this->service->createForwardContract('USD', 50_000, 15500 * 1_000_000, '2026-12-31');
        $hedgeSM = app(HedgeContractStateMachine::class);

        // Mencoba transisi langsung dari draft ke settled -> Ilegal!
        $this->expectException(HedgeInvalidTransitionException::class);
        $hedgeSM->transition($contract, HedgeStatus::SETTLED, 1, 'Illegal jump');
    }

    public function test_hedge_maker_checker_violation_guard(): void
    {
        $creatorUser = User::factory()->create(['name' => 'Maker Dealer', 'role' => 'treasury']);
        $contract = $this->service->createForwardContract(
            currency: 'USD',
            notionalForeignAmount: 50_000,
            forwardRateScaled: 15500 * 1_000_000,
            maturityDate: '2026-12-31',
            creatorId: $creatorUser->id
        );
        $hedgeSM = app(HedgeContractStateMachine::class);

        // Pembuat mencoba menyetujui kontraknya sendiri -> Ditolak!
        $this->expectException(HedgeMakerCheckerViolationException::class);
        $hedgeSM->transition($contract, HedgeStatus::APPROVED, $creatorUser->id, 'Self-approval attempt');
    }

    public function test_money_integrity_blocks_unbalanced_ledger_posting_sabotage(): void
    {
        $this->expectException(\Modules\Banking\Domain\Exceptions\UnbalancedTransactionException::class);

        $lines = \Modules\Banking\Support\Posting::lines();
        // Sabotase: kredit di kedua sisi sehingga total != 0 (sum != 0)
        $lines->credit('trs:fx_reval_asset_adj:IDR', 100_000, 'IDR')
            ->credit('trs:fx_unrealized_gain_loss:IDR', 100_000, 'IDR');

        $lines->toEntries('IDR');
    }
}
