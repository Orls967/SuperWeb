<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use DomainException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Syariah\Ledger\SyariahAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

/**
 * Service pembukuan syariah (G7-D5 & D19-1).
 * Menolak keras setiap posting transaksi syariah yang menyentuh akun mengandung kata 'interest' atau 'bunga'.
 */
final class ShariahLedgerPoster
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    public function post(PostingDTO $dto): void
    {
        SyariahAccounts::ensureAccountsExist();

        foreach ($dto->entries as $entry) {
            $code = strtolower((string) ($entry->accountCode ?? ''));
            if (str_contains($code, 'interest') || str_contains($code, 'bunga')) {
                throw new DomainException("G7-D5 Pelanggaran Syariah: Transaksi syariah dilarang menyentuh akun berbasis bunga '{$code}'.");
            }
        }

        $this->ledgerService->post($dto);
    }

    /**
     * @param  array<string, int>  $entries
     */
    public function postSimple(
        SyariahTransactionTypes $type,
        string $reference,
        string $description,
        array $entries
    ): void {
        $entryDtos = [];
        foreach ($entries as $key => $val) {
            if (is_array($val) && isset($val['account_code'], $val['amount'])) {
                $accountCode = (string) $val['account_code'];
                $amount = (int) $val['amount'];
            } else {
                $accountCode = (string) $key;
                $amount = (int) $val;
            }
            $entryDtos[] = PostingEntryDTO::forCode($accountCode, 'IDR', $amount);
        }

        $dto = new PostingDTO(
            type: $type->value,
            description: $description,
            idempotencyKey: 'syb:post:'.hash('sha256', $type->value.':'.$reference),
            entries: $entryDtos,
            referenceType: 'SYB_TRANSACTION',
            referenceId: $reference,
        );

        $this->post($dto);
    }
}
