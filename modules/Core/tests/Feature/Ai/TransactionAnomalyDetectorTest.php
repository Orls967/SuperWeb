<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\Ai\TransactionAnomalyDetectorService;
use Modules\Core\Domain\Models\CoreAiAnomalyQuarantine;
use Modules\Core\Domain\Models\CoreAiDecisionSnapshot;

uses(RefreshDatabase::class);

test('evaluates normal transactions as low risk without triggering quarantine', function (): void {
    $engine = new TransactionAnomalyDetectorService;

    $result = $engine->evaluateTransaction(
        transactionRef: 'TX-NORMAL-001',
        customerRef: 'CUST-REGULAR-123',
        amountIdr: 150000,
        channel: 'POS',
        recentTxCount10m: 1,
        avgHistoricalAmountIdr: 140000,
        stdDevHistoricalAmountIdr: 20000,
        isLateNight: false,
        isSplitBillPattern: false,
        seed: 101
    );

    expect($result->riskScore)->toBeLessThan(0.40)
        ->and($result->isQuarantined)->toBeFalse()
        ->and($result->quarantineCode)->toBeNull();

    $quarantineCount = CoreAiAnomalyQuarantine::where('transaction_ref', 'TX-NORMAL-001')->count();
    expect($quarantineCount)->toBe(0);
});

test('triggers automatic quarantine and records reasons when multivariate risk score reaches 0.80 threshold', function (): void {
    $engine = new TransactionAnomalyDetectorService;

    // Anomalous transaction: 15x normal spend, 8 transactions in 10 mins, late night, split bill pattern
    $result = $engine->evaluateTransaction(
        transactionRef: 'TX-FRAUD-999',
        customerRef: 'CUST-ATTACKER-666',
        amountIdr: 75000000,
        channel: 'API_GATEWAY',
        recentTxCount10m: 8,
        avgHistoricalAmountIdr: 2000000,
        stdDevHistoricalAmountIdr: 500000,
        isLateNight: true,
        isSplitBillPattern: true,
        seed: 202
    );

    expect($result->riskScore)->toBeGreaterThanOrEqual(0.80)
        ->and($result->isQuarantined)->toBeTrue()
        ->and($result->quarantineCode)->not->toBeNull()
        ->and($result->reasons)->not->toBeEmpty();

    $quarantine = CoreAiAnomalyQuarantine::where('transaction_ref', 'TX-FRAUD-999')->first();
    expect($quarantine)->not->toBeNull()
        ->and($quarantine->status)->toBe('QUARANTINED')
        ->and($quarantine->risk_score)->toBe($result->riskScore)
        ->and($quarantine->amount_idr)->toBe(75000000);
});

test('ensures decision snapshots are cryptographically reproducible', function (): void {
    $engine = new TransactionAnomalyDetectorService;

    $eval1 = $engine->evaluateTransaction(
        transactionRef: 'TX-REPEAT-001',
        customerRef: 'CUST-REPEAT-111',
        amountIdr: 5000000,
        channel: 'MOBILE',
        recentTxCount10m: 3,
        avgHistoricalAmountIdr: 1000000,
        seed: 555
    );

    $eval2 = $engine->evaluateTransaction(
        transactionRef: 'TX-REPEAT-001',
        customerRef: 'CUST-REPEAT-111',
        amountIdr: 5000000,
        channel: 'MOBILE',
        recentTxCount10m: 3,
        avgHistoricalAmountIdr: 1000000,
        seed: 555
    );

    expect($eval1->riskScore)->toBe($eval2->riskScore)
        ->and($eval1->decisionHash)->toBe($eval2->decisionHash);

    $snapshot = CoreAiDecisionSnapshot::where('decision_code', $eval1->decisionCode)->first();
    expect($snapshot)->not->toBeNull()
        ->and($snapshot->model_name)->toBe('TRANSACTION_ANOMALY_DETECTOR');
});
