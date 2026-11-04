<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Ai;

use Illuminate\Support\Str;
use Modules\Core\Application\DTOs\Ai\AnomalyEvaluationResult;
use Modules\Core\Contracts\TransactionAnomalyDetectorInterface;
use Modules\Core\Domain\Models\CoreAiAnomalyQuarantine;
use Modules\Core\Domain\Models\CoreAiDecisionSnapshot;
use Modules\Shared\Domain\Algorithms\Stats\ModifiedZScore;

class TransactionAnomalyDetectorService implements TransactionAnomalyDetectorInterface
{
    private readonly ModifiedZScore $modifiedZScore;

    private readonly DeterministicInferenceEngine $inferenceEngine;

    public function __construct(
        ?ModifiedZScore $modifiedZScore = null,
        ?DeterministicInferenceEngine $inferenceEngine = null
    ) {
        $this->modifiedZScore = $modifiedZScore ?? new ModifiedZScore;
        $this->inferenceEngine = $inferenceEngine ?? new DeterministicInferenceEngine;
    }

    /**
     * @param  list<int>  $historicalAmounts
     */
    public function evaluateTransaction(
        string $transactionRef,
        string $customerRef,
        int $amountIdr,
        string $channel = 'ONLINE',
        int $recentTxCount10m = 1,
        int $avgHistoricalAmountIdr = 0,
        int $stdDevHistoricalAmountIdr = 0,
        bool $isLateNight = false,
        bool $isSplitBillPattern = false,
        int $seed = 42,
        array $historicalAmounts = []
    ): AnomalyEvaluationResult {
        $reasons = [];
        $isModifiedZAnomaly = false;
        $modifiedZFormatted = '0.000';

        // 1. Modified Z-Score Evaluator (D64-2)
        if ($historicalAmounts !== []) {
            $zRes = $this->modifiedZScore->evaluate($amountIdr, $historicalAmounts);
            $modifiedZFormatted = $zRes->zFormatted;
            if ($zRes->isAnomaly) {
                $isModifiedZAnomaly = true;
                $reasons[] = "Skor Modified Z-Score ({$modifiedZFormatted}) melampaui ambang anomali (|z| > 3.5)";
            }
            $zScoreNormalized = $isModifiedZAnomaly ? 0.95 : min(0.60, max(0.0, abs((float) $modifiedZFormatted) / 3.5));
        } elseif ($avgHistoricalAmountIdr > 0) {
            $stdDev = max(1, $stdDevHistoricalAmountIdr ?: (int) round($avgHistoricalAmountIdr * 0.30));
            $z = ($amountIdr - $avgHistoricalAmountIdr) / (float) $stdDev;
            $zScoreNormalized = min(1.0, max(0.0, $z / 3.0));
            if ($zScoreNormalized >= 0.70) {
                $reasons[] = 'Nominal transaksi Rp '.number_format($amountIdr).' melebihi ambang deviasi historis (Z-Score: '.round($z, 2).')';
            }
        } else {
            $zScoreNormalized = $amountIdr > 50_000_000 ? 0.85 : 0.20;
            if ($amountIdr > 50_000_000) {
                $reasons[] = 'Transaksi nominal besar Rp '.number_format($amountIdr).' pada akun tanpa riwayat historis';
            }
        }

        // 2. Velocity Burst Check (Weight: 0.30)
        $velocityScore = min(1.0, max(0.0, $recentTxCount10m / 5.0));
        if ($recentTxCount10m >= 5) {
            $reasons[] = "Frekuensi transaksi mencurigakan ({$recentTxCount10m} mutasi dalam jendela 10 menit)";
        }

        // 3. Channel & Timing Risk (Weight: 0.15)
        $channelScore = $isLateNight ? 1.0 : 0.0;
        if ($isLateNight) {
            $reasons[] = 'Transaksi dieksekusi pada jam berisiko tinggi tanpa otentikasi biometrik tambahan';
        }

        // 4. Split-Bill Pattern (Weight: 0.15)
        $splitBillScore = $isSplitBillPattern ? 1.0 : 0.0;
        if ($isSplitBillPattern) {
            $reasons[] = 'Terdeteksi pola pemecahan tagihan berulang (split-bill structuring)';
        }

        // Multivariate Risk Score
        $riskScore = round(
            (0.40 * $zScoreNormalized) +
            (0.30 * $velocityScore) +
            (0.15 * $channelScore) +
            (0.15 * $splitBillScore),
            4
        );

        if ($isModifiedZAnomaly) {
            $riskScore = max($riskScore, 0.90);
        }

        $isQuarantined = $riskScore >= 0.80 || $isModifiedZAnomaly;
        if ($isQuarantined && empty($reasons)) {
            $reasons[] = "Skor risiko gabungan melampaui ambang batas karantina (Skor: {$riskScore})";
        }

        $decisionCode = 'DEC-ANM-'.Str::upper(Str::random(10));
        $snapshotId = (string) Str::uuid();

        $inputSnapshot = [
            'transaction_ref' => $transactionRef,
            'customer_ref' => $customerRef,
            'amount_idr' => $amountIdr,
            'channel' => $channel,
            'recent_tx_count_10m' => $recentTxCount10m,
            'avg_historical_amount_idr' => $avgHistoricalAmountIdr,
            'std_dev_historical_amount_idr' => $stdDevHistoricalAmountIdr,
            'is_late_night' => $isLateNight,
            'is_split_bill_pattern' => $isSplitBillPattern,
            'historical_amounts' => $historicalAmounts,
        ];

        $decisionOutput = [
            'risk_score' => $riskScore,
            'is_quarantined' => $isQuarantined,
            'reasons' => $reasons,
            'weights' => [
                'z_score' => round($zScoreNormalized, 4),
                'velocity' => round($velocityScore, 4),
                'channel' => round($channelScore, 4),
                'split_bill' => round($splitBillScore, 4),
            ],
            'modified_z_score' => $modifiedZFormatted,
        ];

        $decisionHash = hash('sha256', (string) $seed.json_encode($inputSnapshot).json_encode($decisionOutput));

        CoreAiDecisionSnapshot::create([
            'id' => $snapshotId,
            'decision_code' => $decisionCode,
            'model_name' => 'TRANSACTION_ANOMALY_DETECTOR',
            'model_version' => '1.0.0',
            'seed' => (string) $seed,
            'input_snapshot' => $inputSnapshot,
            'decision_output' => $decisionOutput,
            'decision_hash' => $decisionHash,
        ]);

        // Catat inferensi deterministik ke core_ai_inferences
        $this->inferenceEngine->inferAndRecord(
            modelCode: 'ANOMALY_DETECTOR',
            version: '1.0.0',
            input: [
                'amount_idr' => $amountIdr,
                'history' => $historicalAmounts !== [] ? $historicalAmounts : [$amountIdr],
            ],
            seed: $seed
        );

        $quarantineCode = null;
        if ($isQuarantined) {
            $quarantine = CoreAiAnomalyQuarantine::where('transaction_ref', $transactionRef)->first();
            if ($quarantine === null) {
                $quarantine = CoreAiAnomalyQuarantine::create([
                    'id' => (string) Str::uuid(),
                    'quarantine_code' => 'QRN-'.Str::upper(Str::random(8)),
                    'transaction_ref' => $transactionRef,
                    'customer_ref' => $customerRef,
                    'amount_idr' => $amountIdr,
                    'risk_score' => $riskScore,
                    'anomaly_reasons' => $reasons,
                    'decision_snapshot_id' => $snapshotId,
                    'status' => 'QUARANTINED',
                    'quarantined_at' => now(),
                ]);
            }
            $quarantineCode = $quarantine->quarantine_code;
        }

        return new AnomalyEvaluationResult(
            transactionRef: $transactionRef,
            riskScore: $riskScore,
            isQuarantined: $isQuarantined,
            reasons: $reasons,
            quarantineCode: $quarantineCode,
            metrics: [
                'z_score_normalized' => round($zScoreNormalized, 4),
                'velocity_score' => round($velocityScore, 4),
                'channel_score' => round($channelScore, 4),
                'split_bill_score' => round($splitBillScore, 4),
                'modified_z_score' => $modifiedZFormatted,
            ],
            decisionHash: $decisionHash,
            decisionCode: $decisionCode,
        );
    }
}
