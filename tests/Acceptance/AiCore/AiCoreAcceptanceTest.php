<?php

declare(strict_types=1);

namespace Tests\Acceptance\AiCore;

use App\Models\User;
use App\Quality\Audit\Fixtures\AiAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\Ai\DeterministicInferenceEngine;
use Modules\Core\Application\Services\Ai\SettlementGuardService;
use Modules\Core\Application\Services\Ai\StockPrescriptionEngineService;
use Modules\Core\Application\Services\Ai\TransactionAnomalyDetectorService;
use Modules\Core\Domain\Exceptions\TransactionQuarantinedException;
use Modules\Core\Domain\Models\CoreAiAnomalyQuarantine;
use Modules\Core\Domain\Models\CoreAiInference;
use Modules\Core\Domain\Models\CoreAiStockRecommendation;
use Tests\TestCase;

class AiCoreAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Matriks akses rute & autorisasi peran (D64-1..D64-4).
     */
    public function test_1_route_access_and_role_authorization_matrix(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $riskOfficer = User::factory()->create(['role' => 'risk_officer']);
        $riskManager = User::factory()->create(['role' => 'risk_manager']);
        $planner = User::factory()->create(['role' => 'planner']);
        $plannerManager = User::factory()->create(['role' => 'planner_manager']);
        $unauthorizedCustomer = User::factory()->create(['role' => 'customer']);

        // 1. Authorized roles
        $this->actingAs($riskOfficer)
            ->get(route('core.ai.anomalies.index'))
            ->assertOk();

        $this->actingAs($riskOfficer)
            ->get(route('core.ai.quarantine.index'))
            ->assertOk();

        $this->actingAs($planner)
            ->get(route('core.ai.stock-recommendations.index'))
            ->assertOk();

        // 2. Unauthorized role forbidden (403)
        $this->actingAs($unauthorizedCustomer)
            ->get(route('core.ai.anomalies.index'))
            ->assertForbidden();

        $this->actingAs($unauthorizedCustomer)
            ->get(route('core.ai.quarantine.index'))
            ->assertForbidden();

        $this->actingAs($unauthorizedCustomer)
            ->get(route('core.ai.stock-recommendations.index'))
            ->assertForbidden();

        // 3. Unauthenticated redirect (302)
        auth()->logout();
        $this->get(route('core.ai.anomalies.index'))
            ->assertRedirect(route('login'));
    }

    /**
     * Test 2: Alur utama end-to-end deteksi anomali, karantina, dan pemblokiran settlement (D64-2).
     */
    public function test_2_e2e_anomaly_detection_quarantine_and_settlement_blocked(): void
    {
        $detector = app(TransactionAnomalyDetectorService::class);
        $settlementGuard = app(SettlementGuardService::class);

        // A. Transaksi normal: tidak dikarantina
        $normalRes = $detector->evaluateTransaction(
            transactionRef: 'TX-NORM-001',
            customerRef: 'CUST-001',
            amountIdr: 100_000,
            historicalAmounts: [98_000, 100_000, 102_000, 99_000, 101_000],
            seed: 42
        );

        expect($normalRes->isQuarantined)->toBeFalse();
        expect($settlementGuard->isSettlementAllowed('TX-NORM-001'))->toBeTrue();

        // B. Transaksi anomali: deviasi ekstrem (vektor ModifiedZScore z > 3.5)
        $anomalyRes = $detector->evaluateTransaction(
            transactionRef: 'TX-FRAUD-999',
            customerRef: 'CUST-002',
            amountIdr: 15_000_000,
            historicalAmounts: [100_000, 102_000, 98_000, 101_000, 99_000, 100_000, 103_000, 97_000, 100_000, 15_000_000],
            seed: 42
        );

        expect($anomalyRes->isQuarantined)->toBeTrue();
        expect(CoreAiAnomalyQuarantine::where('transaction_ref', 'TX-FRAUD-999')->exists())->toBeTrue();

        // C. Settlement menolak transaksi terkarantina
        expect($settlementGuard->isSettlementAllowed('TX-FRAUD-999'))->toBeFalse();

        $this->expectException(TransactionQuarantinedException::class);
        $settlementGuard->assertAllowed('TX-FRAUD-999');
    }

    /**
     * Test 3: Jalur galat four-eyes approval rilis karantina dan rilis idempoten (D64-2).
     */
    public function test_3_four_eyes_approval_and_idempotent_release(): void
    {
        $initiator = User::factory()->create(['role' => 'risk_officer', 'name' => 'Officer A']);
        $sameOfficer = $initiator;
        $manager = User::factory()->create(['role' => 'risk_manager', 'name' => 'Manager B']);

        $quarantine = CoreAiAnomalyQuarantine::create([
            'quarantine_code' => 'QRN-TEST-001',
            'transaction_ref' => 'TX-QUAR-001',
            'customer_ref' => 'CUST-999',
            'amount_idr' => 25_000_000,
            'risk_score' => 0.95,
            'anomaly_reasons' => ['EXTREME_DEVIATION'],
            'status' => 'QUARANTINED',
            'quarantined_at' => now(),
            'created_by' => $initiator->name,
        ]);

        // 1. Inisiator sendiri mencoba merilis -> ditolak (Four-eyes violation: HTTP 403 / redirect error)
        $this->actingAs($sameOfficer)
            ->post(route('core.ai.quarantine.release', $quarantine), [
                'release_justification' => 'Otorisasi sendiri oleh pembuat',
            ])
            ->assertForbidden();

        // 2. Risk Manager yang berbeda merilis -> sukses
        $this->actingAs($manager)
            ->post(route('core.ai.quarantine.release', $quarantine), [
                'release_justification' => 'Dokumen pendukung valid setelah investigasi mendalam',
            ])
            ->assertRedirect();

        $quarantine->refresh();
        expect($quarantine->status)->toBe('RELEASED')
            ->and($quarantine->released_by)->toBe($manager->name);

        // 3. Rilis 2x -> idempoten, tidak menduplikasi rilis atau error
        $this->actingAs($manager)
            ->post(route('core.ai.quarantine.release', $quarantine), [
                'release_justification' => 'Mencoba rilis ulang transaksi yang sudah bebas',
            ])
            ->assertRedirect();

        expect(CoreAiAnomalyQuarantine::where('transaction_ref', 'TX-QUAR-001')->count())->toBe(1);
    }

    /**
     * Test 4: Otorisasi rekomendasi stok dan persetujuan planner_manager (D64-3).
     */
    public function test_4_stock_recommendation_planner_manager_approval(): void
    {
        $planner = User::factory()->create(['role' => 'planner']);
        $plannerManager = User::factory()->create(['role' => 'planner_manager']);

        // Generate rekomendasi bernilai tinggi > Rp 10.000.000
        $engine = app(StockPrescriptionEngineService::class);
        $result = $engine->generateRecommendation(
            itemCode: 'SKU-EXPENSIVE-1',
            itemName: 'Komponen Mesin Turbo Industri',
            currentStock: 10,
            avgDailyDemand: 50,
            leadTimeDays: 7,
            unitCostIdr: 500_000, // Nilai > Rp 10.000.000
            historicalDemands: [100, 120, 110, 130],
            seasonCode: 'REGULAR',
            seed: 42
        );

        $rec = CoreAiStockRecommendation::where('item_code', 'SKU-EXPENSIVE-1')->firstOrFail();
        expect($rec->requires_human_approval)->toBeTrue();
        expect($rec->status)->toBe('PROPOSED');

        // Planner biasa tidak boleh menyetujui rekomendasi berstatus butuh approval
        $this->actingAs($planner)
            ->post(route('core.ai.stock-recommendations.approve', $rec))
            ->assertForbidden();

        // Planner Manager berhak menyetujui
        $this->actingAs($plannerManager)
            ->post(route('core.ai.stock-recommendations.approve', $rec))
            ->assertRedirect();

        $rec->refresh();
        expect($rec->status)->toBe('APPROVED_AS_PO')
            ->and($rec->approved_po_ref)->not->toBeNull();
    }

    /**
     * Test 5: Audit dua sumber ai:audit bersih dan deteksi fixture korupsi (D64-4).
     */
    public function test_5_ai_audit_clean_and_corruption_fixture(): void
    {
        // 1. Kondisi bersih -> exit 0
        $this->artisan('ai:audit')
            ->assertSuccessful()
            ->expectsOutputToContain('AI audit PASSED: All decision snapshots and invariants verified clean');

        // 2. Fixture korupsi hash -> exit != 0 dan memunculkan keyword error
        $fixture = new AiAuditCorruptionFixture;
        $keyword = $fixture->corrupt();

        $this->artisan('ai:audit')
            ->assertFailed()
            ->expectsOutputToContain($keyword);
    }

    /**
     * Test 6: Replay inferensi deterministik identik byte-per-byte dan invarian append-only (D64-1).
     */
    public function test_6_deterministic_replay_and_append_only_inference(): void
    {
        $engine = app(DeterministicInferenceEngine::class);

        $modelCode = 'ANOMALY_DETECTOR';
        $version = '1.0.0';
        $input = [
            'amount_idr' => 150,
            'history' => [100, 102, 98, 101, 99, 100, 103, 97, 100, 150],
        ];
        $seed = 12345;

        // Inferensi pertama
        $inf1 = $engine->inferAndRecord($modelCode, $version, $input, $seed);
        expect($inf1)->not->toBeNull();

        // Replay identik
        $replayed = $engine->replay($inf1->id);
        expect($replayed['is_identical'])->toBeTrue()
            ->and($replayed['computed_output'])->toEqual($inf1->output);

        // PR5: Model CoreAiInference menolak update atau delete
        $this->expectException(\RuntimeException::class);
        $inf1->update(['output' => ['tampered' => true]]);
    }
}
