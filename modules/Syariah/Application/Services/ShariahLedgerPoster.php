<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use DomainException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Syariah\Ledger\SyariahAccounts;

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
}
