<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Models\HtlCorporateAccount;

class HotelCorporateDirectDebitBatchOrchestrator
{
    /**
     * Mengelompokkan penagihan auto-debit kliring perbankan (BI-FAST / SKN) untuk rekening korporat terdaftar.
     *
     * @return array{batch_file_id: string, total_debit_amount_idr: int, transactions_count: int, generated_at: string, clearing_channel: string}
     */
    public function generateDirectDebitBatch(int $maxTransactions = 50): array
    {
        $accounts = HtlCorporateAccount::where('status', 'ACTIVE')
            ->where('current_outstanding_idr', '>', 0)
            ->take($maxTransactions)
            ->get();

        $totalDebit = (int) $accounts->sum('current_outstanding_idr');
        $batchId = 'DD-BATCH-'.date('Ymd-His').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        return [
            'batch_file_id' => $batchId,
            'total_debit_amount_idr' => $totalDebit,
            'transactions_count' => $accounts->count(),
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'clearing_channel' => 'BI_FAST_CORPORATE_CLEARING',
        ];
    }
}
