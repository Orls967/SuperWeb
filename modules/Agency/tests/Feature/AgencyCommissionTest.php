<?php

declare(strict_types=1);

namespace Tests\Feature\Agency;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Agency\Application\Services\AgencyService;
use Modules\Agency\Domain\Models\Agent;
use Modules\Agency\Domain\Models\CommissionAccrual;
use Modules\Banking\Domain\Models\LedgerAccount;
use Tests\TestCase;

/**
 * Regresi Fase 45: hirarki/upline, skema flat/percent/slab, attribution
 * first/last-touch + expiry, accrual hold + clawback, payout/withholding
 * four-eyes + ledger, statement, audit.
 */
class AgencyCommissionTest extends TestCase
{
    use RefreshDatabase;

    private AgencyService $agency;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->agency = app(AgencyService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    private function makeAgent(string $code, ?Agent $parent = null): Agent
    {
        return $this->agency->registerAgent([
            'code' => $code, 'name' => "Agen {$code}", 'kind' => 'sales_agent',
            'parent_id' => $parent?->id, 'max_downline_levels' => 3,
        ]);
    }

    private function activate(Agent $agent): Agent
    {
        return $this->agency->transition($agent, 'active');
    }

    private function accountBalance(string $code): int
    {
        return (int) (LedgerAccount::where('code', $code)->value('cached_balance') ?? 0);
    }

    public function test_agent_hierarchy_requires_active_upline(): void
    {
        $root = $this->activate($this->makeAgent('AG-ROOT'));
        $child = $this->activate($this->makeAgent('AG-CHILD', $root));
        $grandchild = $this->makeAgent('AG-GRAND', $child);

        $this->assertSame($root->id, $child->parent_id);
        $this->assertSame($child->id, $grandchild->parent_id);
        $this->assertCount(1, $child->uplines(3));

        $inactive = $this->makeAgent('AG-INACTIVE');
        try {
            $this->makeAgent('AG-BAD-CHILD', $inactive);
            $this->fail('Upline inactive harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('active', $e->getMessage());
        }
    }

    public function test_agent_contract_and_non_compete(): void
    {
        $agent = $this->activate($this->makeAgent('AG-CTR'));
        $contract = $this->agency->createContract($agent, [
            'contract_ref' => 'CTR/2026/0001',
            'territory_scope' => 'Jabodetabek',
            'product_scope' => ['SKU-1', 'SKU-2'],
            'exclusive' => true,
            'non_compete' => true,
            'valid_from' => now()->subDay()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
        ]);

        $this->assertTrue($contract->isActiveAt());
        $this->assertTrue($contract->exclusive);
        $this->assertTrue($contract->non_compete);
        $this->assertSame(['SKU-1', 'SKU-2'], $contract->product_scope);
    }

    public function test_flat_percent_slab_and_target_bonus_calculation(): void
    {
        $agent = $this->activate($this->makeAgent('AG-SCHEME'));

        $flat = $this->agency->saveScheme($agent, [
            'code' => 'FLAT', 'name' => 'Flat', 'basis' => 'flat', 'flat_amount_idr' => 25_000,
            'scope' => 'all', 'valid_from' => now()->subDay()->toDateString(),
        ]);
        $this->assertSame(25_000, $flat->calculate(1_000_000));

        $percent = $this->agency->saveScheme($agent, [
            'code' => 'PCT', 'name' => 'Persen', 'basis' => 'percent', 'rate_percent' => 5,
            'scope' => 'all', 'valid_from' => now()->subDay()->toDateString(),
        ]);
        $this->assertSame(50_000, $percent->calculate(1_000_000));

        $slab = $this->agency->saveScheme($agent, [
            'code' => 'SLAB', 'name' => 'Slab', 'basis' => 'slab',
            'slabs' => [['min_amount_idr' => 0, 'rate_percent' => 2], ['min_amount_idr' => 1_000_000, 'rate_percent' => 5]],
            'scope' => 'all', 'valid_from' => now()->subDay()->toDateString(),
        ]);
        $this->assertSame(100_000, $slab->calculate(2_000_000));

        $bonus = $this->agency->saveScheme($agent, [
            'code' => 'BONUS', 'name' => 'Bonus target', 'basis' => 'target_bonus',
            'target_amount_idr' => 2_000_000, 'bonus_amount_idr' => 100_000,
            'scope' => 'all', 'valid_from' => now()->subDay()->toDateString(),
        ]);
        $this->assertSame(100_000, $bonus->calculate(2_000_000));
        $this->assertSame(0, $bonus->calculate(1_999_999));
    }

    public function test_first_touch_last_touch_and_expiry(): void
    {
        $a = $this->activate($this->makeAgent('AG-FIRST'));
        $b = $this->activate($this->makeAgent('AG-LAST'));

        $this->agency->recordAttribution([
            'agent_id' => $a->id, 'reference_id' => 'LEAD-1', 'rule' => 'first_touch',
            'source' => 'referral', 'touched_at' => now()->subDay()->toDateString(),
            'expires_at' => now()->addMonth()->toDateString(),
        ]);
        $ignored = $this->agency->recordAttribution([
            'agent_id' => $b->id, 'reference_id' => 'LEAD-1', 'rule' => 'first_touch',
            'source' => 'lead', 'touched_at' => now()->toDateString(),
        ]);
        $this->assertSame($a->id, $ignored->agent_id, 'First-touch mempertahankan agen pertama.');

        $last = $this->agency->recordAttribution([
            'agent_id' => $b->id, 'reference_id' => 'LEAD-1', 'rule' => 'last_touch',
            'source' => 'agent_code', 'touched_at' => now()->toDateString(),
        ]);
        $this->assertSame($b->id, $last->agent_id, 'Last-touch mengganti agen.');
        $this->assertSame($b->id, $this->agency->resolveAttribution('LEAD-1')?->agent_id);

        $expired = $this->agency->recordAttribution([
            'agent_id' => $a->id, 'reference_id' => 'LEAD-EXPIRED', 'rule' => 'last_touch',
            'touched_at' => now()->subMonths(2)->toDateString(), 'expires_at' => now()->subDay()->toDateString(),
        ]);
        $this->assertTrue($expired->isExpired());
        $this->assertNull($this->agency->resolveAttribution('LEAD-EXPIRED'));
    }

    public function test_accrual_holds_until_return_window_and_upline_override(): void
    {
        $upline = $this->activate($this->makeAgent('AG-UP'));
        $agent = $this->activate($this->makeAgent('AG-DOWN', $upline));

        $this->agency->saveScheme($agent, [
            'code' => 'DIRECT', 'name' => 'Direct 10%', 'basis' => 'percent', 'rate_percent' => 10,
            'scope' => 'all', 'level' => 0, 'valid_from' => now()->subDay()->toDateString(),
        ]);
        $this->agency->saveScheme($upline, [
            'code' => 'OVERRIDE', 'name' => 'Override upline 2%', 'basis' => 'percent',
            'rate_percent' => 0, 'override_rate_percent' => 2, 'scope' => 'all', 'level' => 1,
            'valid_from' => now()->subDay()->toDateString(),
        ]);

        $accrual = $this->agency->accrueSale($agent, 'ORD-100', 1_000_000, 30, $this->admin);
        $this->assertSame(100_000, (int) $accrual->amount_idr);
        $this->assertSame('hold', $accrual->status);
        $this->assertSame(now()->addDays(30)->toDateString(), $accrual->hold_until->toDateString());

        $override = CommissionAccrual::where('agent_id', $upline->id)->where('source_type', 'override')->firstOrFail();
        $this->assertSame(20_000, (int) $override->amount_idr);
        $this->assertSame('hold', $override->status);

        $again = $this->agency->accrueSale($agent, 'ORD-100', 1_000_000, 30, $this->admin);
        $this->assertSame($accrual->id, $again->id);

        $accrual->update(['hold_until' => now()->subDay()->toDateString()]);
        $this->assertGreaterThan(0, $this->agency->releaseHolds());
        $this->assertSame('payable', $accrual->fresh()->status);
    }

    public function test_clawback_is_negative_and_offsets_payable(): void
    {
        $agent = $this->activate($this->makeAgent('AG-CLAW'));
        $this->agency->saveScheme($agent, [
            'code' => 'PCT', 'name' => 'Persen', 'basis' => 'percent', 'rate_percent' => 10,
            'scope' => 'all', 'valid_from' => now()->subDay()->toDateString(),
        ]);
        $accrual = $this->agency->accrueSale($agent, 'ORD-R', 500_000, 0, $this->admin);
        $this->assertSame(-50_000, $this->accountBalance(AgencyService::ACCT_PAYABLE), 'Kredit payable = cached negatif.');

        $clawback = $this->agency->recordClawback($accrual->id, 30_000, 'Retur pelanggan', $this->admin);
        $this->assertSame(-30_000, (int) $clawback->amount_idr);
        $this->assertSame('reversed', $accrual->fresh()->status);
        $this->assertSame(-20_000, $this->accountBalance(AgencyService::ACCT_PAYABLE));
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_payout_four_eyes_withholding_and_ledger(): void
    {
        $agent = $this->activate($this->makeAgent('AG-PAY'));
        $this->agency->saveScheme($agent, [
            'code' => 'PCT', 'name' => 'Persen', 'basis' => 'percent', 'rate_percent' => 10,
            'scope' => 'all', 'valid_from' => now()->subDay()->toDateString(),
        ]);
        $this->agency->accrueSale($agent, 'ORD-P', 1_000_000, 0, $this->admin);

        $payout = $this->agency->createPayout($agent, now()->format('Y'), 2, $this->admin);
        $this->assertSame(100_000, (int) $payout->gross_idr);
        $this->assertSame(2_000, (int) $payout->withheld_tax_idr);
        $this->assertSame(98_000, (int) $payout->net_idr);
        $this->assertSame('pending', $payout->status);

        $reviewerA = User::factory()->create(['role' => 'admin']);
        $approved = $this->agency->approvePayout($payout, $reviewerA);
        $this->assertSame('approved', $approved->status);

        // Akun shared (clearing/tax) dipakai modul lain → ukur delta sebelum bayar.
        $clearingBefore = $this->accountBalance(AgencyService::ACCT_CLEARING);
        $taxBefore = $this->accountBalance(AgencyService::ACCT_TAX);

        $paid = $this->agency->payPayout($approved, $this->admin, 'BANK-REF-1');
        $this->assertSame('paid', $paid->status);
        $this->assertNotNull($paid->ledger_transaction_id);
        $this->assertSame('paid', CommissionAccrual::where('agent_id', $agent->id)->firstOrFail()->status);
        $this->assertSame(0, $this->accountBalance(AgencyService::ACCT_PAYABLE), 'Accrual (CR 100k) + payout (DR 98k+2k) = netral.');
        $this->assertSame(-98_000, $this->accountBalance(AgencyService::ACCT_CLEARING) - $clearingBefore);
        $this->assertSame(-2_000, $this->accountBalance(AgencyService::ACCT_TAX) - $taxBefore);
        $this->artisan('bank:reconcile')->assertSuccessful();

        // Replay tidak membayar ulang: saldo clearing tidak berubah.
        $clearingMid = $this->accountBalance(AgencyService::ACCT_CLEARING);
        $again = $this->agency->payPayout($paid, $this->admin, 'BANK-REF-1');
        $this->assertSame($paid->id, $again->id);
        $this->assertSame($clearingMid, $this->accountBalance(AgencyService::ACCT_CLEARING));
    }

    public function test_statement_balances_accrual_clawback_and_paid(): void
    {
        $agent = $this->activate($this->makeAgent('AG-STM'));
        $this->agency->saveScheme($agent, [
            'code' => 'PCT', 'name' => 'Persen', 'basis' => 'percent', 'rate_percent' => 10,
            'scope' => 'all', 'valid_from' => now()->subDay()->toDateString(),
        ]);
        $accrual = $this->agency->accrueSale($agent, 'ORD-ST', 1_000_000, 0, $this->admin);
        $this->agency->recordClawback($accrual->id, 20_000, 'Retur', $this->admin);

        $statement = $this->agency->buildStatement($agent, now()->format('Y'));
        $this->assertSame(100_000, (int) $statement->accrued_idr);
        $this->assertSame(20_000, (int) $statement->clawback_idr);
        $this->assertSame(0, (int) $statement->paid_idr);
        $this->assertSame(80_000, (int) $statement->closing_balance_idr);
    }

    public function test_agency_pages_and_audit(): void
    {
        $agent = $this->activate($this->makeAgent('AG-PORTAL'));
        $this->actingAs($this->admin)
            ->get(route('agency.index'))
            ->assertOk()
            ->assertSee('Agensi');

        $this->actingAs($this->admin)
            ->get(route('agency.show', $agent))
            ->assertOk()
            ->assertSee('Saldo statement');

        $this->artisan('agy:audit')->assertSuccessful();
    }
}
