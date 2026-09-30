<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Enums\ShiftStatus;
use Modules\Resto\Domain\Exceptions\InvalidOrderOperationException;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\Shift;

class CloseShiftAction
{
    public function __construct(
        private readonly Ledger $ledger
    ) {}

    public function handle(Shift $shift, int $countedCash, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($shift, $countedCash, $note) {
            $lockedShift = Shift::where('id', $shift->id)->lockForUpdate()->firstOrFail();

            if ($lockedShift->status !== ShiftStatus::OPEN) {
                throw new InvalidOrderOperationException("Shift #{$lockedShift->id} tidak berstatus OPEN.");
            }

            // Sum cash sales during this shift
            $cashOrdersTotal = (int) Order::where('shift_id', $lockedShift->id)
                ->where('status', OrderStatus::PAID)
                ->where('payment_method', 'cash')
                ->sum('grand_total');

            $expectedCash = $lockedShift->opening_float + $cashOrdersTotal;
            $variance = $countedCash - $expectedCash;

            // Post variance to ledger if non-zero
            if ($variance !== 0) {
                $outlet = $lockedShift->outlet;
                $outletCode = $outlet?->code ?: "OUT-{$lockedShift->outlet_id}";
                $cashAccCode = "cash:drawer:{$outletCode}:IDR";
                $varAccCode = 'expense:resto:cash_variance:IDR';

                $this->ensureLedgerAccountExists($cashAccCode, "Kas Fisik Kasir {$outlet?->name}", AccountKind::CASH);
                $this->ensureLedgerAccountExists($varAccCode, 'Selisih Kas Fisik Kasir Resto', AccountKind::EXPENSE);

                $varianceBd = BigDecimal::of($variance);

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::CASH_VARIANCE->value,
                    description: "Selisih kas tutup shift #{$lockedShift->id} ({$lockedShift->cashier?->name}): Rp ".number_format($variance, 0, ',', '.'),
                    idempotencyKey: "resto:shift:variance:{$lockedShift->id}:".Str::uuid(),
                    entries: [
                        PostingEntryDTO::forCode($cashAccCode, 'IDR', $varianceBd->negated()),
                        PostingEntryDTO::forCode($varAccCode, 'IDR', $varianceBd),
                    ],
                    referenceType: 'resto_shift',
                    referenceId: $lockedShift->id,
                    createdBy: $lockedShift->cashier_id
                ));
            }

            $lockedShift->expected_cash = $expectedCash;
            $lockedShift->counted_cash = $countedCash;
            $lockedShift->variance = $variance;
            $lockedShift->status = ShiftStatus::CLOSED;
            $lockedShift->closed_at = now();
            if ($note) {
                $lockedShift->note = trim(($lockedShift->note ? $lockedShift->note.' | ' : '').$note);
            }
            $lockedShift->save();

            return $lockedShift;
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
