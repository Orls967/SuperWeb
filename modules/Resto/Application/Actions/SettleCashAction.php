<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Resto\Domain\Models\Outlet;

class SettleCashAction
{
    public function __construct(
        private readonly Ledger $ledger
    ) {}

    public function handle(int $outletId, int $amount, ?int $userId = null, ?string $note = null): LedgerTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal setoran kas harus lebih besar dari 0.');
        }

        $outlet = Outlet::findOrFail($outletId);
        $outletCode = $outlet->code ?: "OUT-{$outlet->id}";

        return DB::transaction(function () use ($outlet, $outletCode, $amount, $userId, $note) {
            $cashAccCode = "cash:drawer:{$outletCode}:IDR";
            $clearingAccCode = 'clearing:external:IDR';

            $this->ensureLedgerAccountExists($cashAccCode, "Kas Fisik Kasir {$outlet->name}", AccountKind::CASH);
            $this->ensureLedgerAccountExists($clearingAccCode, 'Rekening Kliring Eksternal IDR', AccountKind::CLEARING);

            $amountBd = BigDecimal::of($amount);

            return $this->ledger->post(new PostingDTO(
                type: TransactionType::SETTLEMENT->value,
                description: "Setoran kas laci kasir {$outlet->name} ke bank: Rp ".number_format($amount, 0, ',', '.').($note ? " ($note)" : ''),
                idempotencyKey: "resto:settle:cash:{$outlet->id}:".Str::uuid(),
                entries: [
                    PostingEntryDTO::forCode($cashAccCode, 'IDR', $amountBd),
                    PostingEntryDTO::forCode($clearingAccCode, 'IDR', $amountBd->negated()),
                ],
                referenceType: 'resto_outlet',
                referenceId: $outlet->id,
                createdBy: $userId
            ));
        });
    }

    private function ensureLedgerAccountExists(string $code, string $name, AccountKind $kind): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'asset_code' => 'IDR',
                'kind' => $kind->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
