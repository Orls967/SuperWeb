<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Domain\Models\EliminationEntry;

class GenerateIntercompanyEliminationJournalAction
{
    /**
     * @param array{
     *     period: string,
     *     elimination_type: string,
     *     debit_account: string,
     *     credit_account: string,
     *     amount_idr: int,
     *     elimination_code?: string
     * } $data
     */
    public function execute(array $data): EliminationEntry
    {
        if ($data['amount_idr'] <= 0) {
            throw new InvalidArgumentException('Elimination journal amount must be greater than zero.');
        }

        $validTypes = ['RECIPROCAL_AR_AP', 'REVENUE_EXPENSE', 'UNREALIZED_INVENTORY_PROFIT'];
        if (! in_array($data['elimination_type'], $validTypes, true)) {
            throw new InvalidArgumentException("Invalid elimination type: {$data['elimination_type']}.");
        }

        $code = $data['elimination_code'] ?? 'ELIM-'.str_replace('-', '', $data['period']).'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        return EliminationEntry::create([
            'elimination_code' => $code,
            'period' => $data['period'],
            'elimination_type' => $data['elimination_type'],
            'debit_account' => $data['debit_account'],
            'credit_account' => $data['credit_account'],
            'amount_idr' => $data['amount_idr'],
            'status' => 'posted',
        ]);
    }
}
