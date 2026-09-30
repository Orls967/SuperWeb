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
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Resto\Domain\Enums\CateringStatus;
use Modules\Resto\Domain\Models\CateringOrder;
use Modules\Shared\Domain\ValueObjects\Money;

class DeliverAndCompleteCateringAction
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway,
        private readonly Ledger $ledger
    ) {}

    public function handle(CateringOrder $cateringOrder): CateringOrder
    {
        return DB::transaction(function () use ($cateringOrder) {
            $locked = CateringOrder::where('id', $cateringOrder->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [CateringStatus::CONFIRMED, CateringStatus::COOKING, CateringStatus::DELIVERED], true)) {
                throw new InvalidArgumentException("Status katering {$locked->status->value} tidak valid untuk diselesaikan.");
            }

            // 1. Capture deposit 30% dari escrow
            $depositIntent = $locked->depositIntent;
            if ($depositIntent && $depositIntent->status->value === 'held') {
                $this->paymentGateway->capture(
                    intent: $depositIntent,
                    finalAmount: Money::idr($locked->deposit_amount),
                    idempotencyKey: "resto:catering:capture:{$locked->id}:".Str::uuid()
                );
            }

            // 2. Charge sisa 70% dari wallet pelanggan
            $remaining = $locked->grand_total - $locked->deposit_amount;
            if ($remaining > 0 && $locked->user_id) {
                $walletAccCode = "wallet:user:{$locked->user_id}:IDR";
                $walletAccount = LedgerAccount::where('code', $walletAccCode)->first();
                if (! $walletAccount || ! $walletAccount->canCover($remaining)) {
                    throw new InsufficientFundsException('Saldo dompet pelanggan tidak mencukupi untuk pelunasan katering Rp '.number_format($remaining, 0, ',', '.'));
                }

                $outlet = $locked->outlet;
                $outletCode = $outlet?->code ?: "OUT-{$locked->outlet_id}";
                $cateringRevAcc = "revenue:resto:{$outletCode}:catering";

                $this->ensureLedgerAccountExists($cateringRevAcc, "Pendapatan Katering Resto {$outlet?->name}", AccountKind::REVENUE);

                $remBd = BigDecimal::of($remaining);

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::PAYMENT->value,
                    description: "Pelunasan sisa 70% katering #{$locked->number}",
                    idempotencyKey: "resto:catering:settle:{$locked->id}:".Str::uuid(),
                    entries: [
                        PostingEntryDTO::forCode($walletAccCode, 'IDR', $remBd->negated()),
                        PostingEntryDTO::forCode($cateringRevAcc, 'IDR', $remBd),
                    ],
                    referenceType: 'resto_catering_order',
                    referenceId: $locked->id,
                    createdBy: $locked->user_id
                ));
            }

            $locked->update([
                'paid_amount' => $locked->grand_total,
                'status' => CateringStatus::COMPLETED,
            ]);

            return $locked;
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
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
