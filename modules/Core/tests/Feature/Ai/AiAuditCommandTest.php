<?php

declare(strict_types=1);

use App\Quality\Audit\Fixtures\AiAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Application\Services\Ai\DynamicPricingEngineService;
use Modules\Core\Domain\Models\CoreAiDecisionSnapshot;

uses(RefreshDatabase::class);

test('ai:audit succeeds on clean state with valid deterministic snapshots', function (): void {
    $engine = new DynamicPricingEngineService;
    $engine->calculatePrice('ITEM-AUDIT-1', 10000, 20, seed: 100);
    $engine->calculatePrice('ITEM-AUDIT-2', 25000, 50, seed: 200);

    $this->artisan('ai:audit')
        ->assertSuccessful()
        ->expectsOutputToContain('AI audit PASSED: All decision snapshots and invariants verified clean');
});

test('ai:audit fails and emits error message when decision hash is corrupted', function (): void {
    $fixture = new AiAuditCorruptionFixture;
    $keyword = $fixture->corrupt();

    $this->artisan('ai:audit')
        ->assertFailed()
        ->expectsOutputToContain($keyword);
});

test('ai:audit fails when dynamic pricing snapshot violates floor price guardrail', function (): void {
    $snapshotId = (string) Str::uuid();
    $input = [
        'item_code' => 'BREACH-FLOOR-ITEM',
        'base_price_idr' => 10000,
        'floor_price_idr' => 8000,
        'ceiling_price_idr' => 15000,
    ];
    $output = [
        'final_price_idr' => 5000, // Illegal: below floor price 8000!
    ];
    $seed = '12345';
    $hash = hash('sha256', $seed.json_encode($input).json_encode($output));

    CoreAiDecisionSnapshot::create([
        'id' => $snapshotId,
        'decision_code' => 'DEC-BREACH-001',
        'model_name' => 'DYNAMIC_PRICING',
        'model_version' => '1.0.0',
        'seed' => $seed,
        'input_snapshot' => $input,
        'decision_output' => $output,
        'decision_hash' => $hash,
    ]);

    $this->artisan('ai:audit')
        ->assertFailed()
        ->expectsOutputToContain('AI audit FAILED: Pricing guardrail breached');
});

test('ai:audit fails when high risk transaction has missing quarantine record', function (): void {
    $snapshotId = (string) Str::uuid();
    $input = [
        'transaction_ref' => 'TX-MISSING-QUARANTINE',
        'customer_ref' => 'CUST-MISSING',
        'amount_idr' => 100000000,
    ];
    $output = [
        'risk_score' => 0.95, // High risk >= 0.80!
        'is_quarantined' => true,
    ];
    $seed = '9999';
    $hash = hash('sha256', $seed.json_encode($input).json_encode($output));

    CoreAiDecisionSnapshot::create([
        'id' => $snapshotId,
        'decision_code' => 'DEC-HIGH-RISK-001',
        'model_name' => 'TRANSACTION_ANOMALY_DETECTOR',
        'model_version' => '1.0.0',
        'seed' => $seed,
        'input_snapshot' => $input,
        'decision_output' => $output,
        'decision_hash' => $hash,
    ]);

    $this->artisan('ai:audit')
        ->assertFailed()
        ->expectsOutputToContain('AI audit FAILED: Missing anomaly quarantine record');
});
