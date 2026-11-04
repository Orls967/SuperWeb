<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Application\DTOs\Ai\AnomalyEvaluationResult;

interface TransactionAnomalyDetectorInterface
{
    /**
     * Evaluate transaction in real-time using multivariate scoring (Modified Z-score, velocity burst, split-bill, channel anomaly).
     * Automatically triggers quarantine in core_ai_anomaly_quarantines if risk score >= 0.80 or modified Z-score is an anomaly.
     *
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
    ): AnomalyEvaluationResult;
}
