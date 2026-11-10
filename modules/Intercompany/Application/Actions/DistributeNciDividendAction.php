<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Application\Services\IntercompanyLedgerPoster;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\NciDividend;
use Modules\Intercompany\Domain\Models\SubsidiaryNci;

class DistributeNciDividendAction
{
    public function __construct(
        protected readonly IntercompanyLedgerPoster $ledgerPoster
    ) {}

    public function execute(
        string $subsidiaryName,
        int $totalDividendIdr,
        string $declarationDate,
        ?int $userId = null
    ): NciDividend {
        $nci = SubsidiaryNci::where('subsidiary_name', $subsidiaryName)->first();
        if (! $nci) {
            throw new InvalidArgumentException("Data kepemilikan NCI untuk [{$subsidiaryName}] tidak ditemukan.");
        }

        if ($totalDividendIdr <= 0) {
            throw new InvalidArgumentException('Total dividen yang dideklarasikan harus lebih besar dari nol.');
        }

        $nciDividend = (int) floor(($totalDividendIdr * $nci->nci_ownership_permille) / 100000);
        $entity = IntercompanyEntity::where('entity_code', $subsidiaryName)->first();

        $record = NciDividend::create([
            'subsidiary_entity_id' => $entity?->id,
            'declaration_date' => $declarationDate,
            'total_dividend_idr' => $totalDividendIdr,
            'nci_dividend_idr' => $nciDividend,
            'status' => 'paid',
        ]);

        $ledgerTx = $this->ledgerPoster->postNciDividendPayment(
            subsidiaryEntity: $subsidiaryName,
            nciDividendIdr: $nciDividend,
            idempotencyKey: "IC:NCI:DIV:{$subsidiaryName}:{$declarationDate}",
            declarationDate: $declarationDate,
            userId: $userId
        );

        $record->ledger_transaction_id = $ledgerTx->id;
        $record->save();

        return $record;
    }
}
