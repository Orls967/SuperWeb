<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CryptoNativeDefiSimulationService;
use Tests\TestCase;

class CryptoNativeDefiSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected CryptoNativeDefiSimulationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CryptoNativeDefiSimulationService::class);
    }

    public function test_multisig_quorum_and_daily_spending_limit(): void
    {
        // 1. Create vault with $500k daily limit (272.1)
        $this->service->createVault('VAULT-TREASURY-01', 'HOT_WALLET', 2000000.0, 500000.0);

        // 2. Propose $200k multi-sig tx (requires 2 signatures) (272.1 & 272.4)
        $tx = $this->service->proposeMultiSigTx('VAULT-TREASURY-01', 200000.0, '0xabc1234567890def', 2);
        $this->assertEquals('PENDING_SIGNATURES', $tx->status);

        // Sign 1: Still pending
        $signed1 = $this->service->signMultiSigTx($tx->tx_code);
        $this->assertEquals('PENDING_SIGNATURES', $signed1->status);

        // Sign 2: Quorum reached -> EXECUTED
        $signed2 = $this->service->signMultiSigTx($tx->tx_code);
        $this->assertEquals('EXECUTED', $signed2->status);

        // 3. Daily limit breach ($200k spent + $400k > $500k limit) rejected (272.1)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Daily spending limit exceeded');
        $this->service->proposeMultiSigTx('VAULT-TREASURY-01', 400000.0, '0x999');
    }

    public function test_emergency_break_glass_procedure(): void
    {
        $this->service->createVault('VAULT-EMERGENCY', 'HOT_WALLET', 100000.0, 100000.0);
        $tx = $this->service->proposeMultiSigTx('VAULT-EMERGENCY', 50000.0, '0xemergency', 5);

        // Break-glass execution without waiting for 5 signatures (272.6 Edge Case)
        $executed = $this->service->executeBreakGlassEmergency(
            $tx->tx_code,
            'Critical smart contract vulnerability patch payment authorized by Board'
        );

        $this->assertEquals('EXECUTED', $executed->status);
        $this->assertTrue((bool) $executed->is_break_glass_emergency);
        $this->assertNotNull($executed->break_glass_justification);
    }

    public function test_defi_amm_constant_product_pool_swap_and_halt_guard(): void
    {
        // 1. Initialize pool: 1,000 X and 2,000 Y => k = 2,000,000 (272.2 & 272.4)
        $pool = $this->service->createLiquidityPool('POOL-NICKEL-USDT', 1000.0, 2000.0);
        $this->assertEquals(2000000.0, (float) $pool->invariant_k);

        // 2. Normal swap in: add 250 X => new X = 1250, new Y = 2,000,000 / 1250 = 1600 (272.2)
        $swapped = $this->service->swapExactTokens('POOL-NICKEL-USDT', 250.0);
        $this->assertEquals(1250.0, (float) $swapped->reserve_x);
        $this->assertEquals(1600.0, (float) $swapped->reserve_y);

        // 3. Disrupted invariant triggers immediate pool halt (272.5 Edge Case)
        try {
            $this->service->swapExactTokens('POOL-NICKEL-USDT', 100.0, tamperInvariant: true);
            $this->fail('Expected exception for disrupted pool invariant');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Immediate halt triggered', $e->getMessage());
        }

        $halted = DB::table('defi_liquidity_pools')->where('pool_symbol', 'POOL-NICKEL-USDT')->first();
        $this->assertTrue((bool) $halted->is_halted);
    }

    public function test_staking_program_emission_cap_and_orderly_closure(): void
    {
        // 1. Create staking program with $10,000 emission cap (272.3 & 272.4)
        $prog = $this->service->createStakingProgram('STAKE-PLATFORM-01', 'AUTOTOKEN', 10000.0);

        // 2. Emit $6,000 rewards succeeds
        $emitted = $this->service->emitStakingRewards('STAKE-PLATFORM-01', 6000.0);
        $this->assertEquals(6000.0, (float) $emitted->emitted_rewards_usd);

        // 3. Emitting beyond cap ($6,000 + $5,000 > $10,000) rejected (272.4)
        try {
            $this->service->emitStakingRewards('STAKE-PLATFORM-01', 5000.0);
            $this->fail('Expected exception for exceeding emission cap');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Tokenomics emission schedule breach', $e->getMessage());
        }

        // 4. Closing program ensures orderly payout completion (272.7 Edge Case)
        $closed = $this->service->closeStakingProgramOrderly('STAKE-PLATFORM-01');
        $this->assertTrue((bool) $closed->is_closed);
        $this->assertTrue((bool) $closed->payouts_completed);
    }

    public function test_crypto_defi_treasury_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->createVault('VAULT-AUD', 'COLD_STORAGE', 10000.0, 5000.0);
        $this->service->createLiquidityPool('POOL-AUD', 100.0, 100.0);
        $this->service->createStakingProgram('STAKE-AUD', 'AUD', 1000.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: staking program exceeding emission cap
        DB::table('defi_staking_programs')->insert([
            'program_code' => 'STAKE-ROGUE-OVERFLOW',
            'token_symbol' => 'BAD',
            'total_staked' => 100.0,
            'emission_cap_usd' => 1000.0,
            'emitted_rewards_usd' => 2500.0, // Discrepancy: exceeded!
            'is_closed' => false,
            'payouts_completed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
