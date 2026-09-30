<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\Actions\VerifyPinAction;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Resto\Domain\Enums\CateringStatus;
use Modules\Resto\Domain\Models\CateringOrder;
use Modules\Shared\Domain\ValueObjects\Money;

class HoldCateringDepositAction
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway,
        private readonly VerifyPinAction $verifyPinAction
    ) {}

    public function handle(
        CateringOrder $cateringOrder,
        User $customerUser,
        ?string $pin = null
    ): CateringOrder {
        if ($pin !== null) {
            $this->verifyPinAction->handle($customerUser, $pin);
        }

        return DB::transaction(function () use ($cateringOrder, $customerUser) {
            $locked = CateringOrder::where('id', $cateringOrder->id)->lockForUpdate()->firstOrFail();

            $outlet = $locked->outlet;
            $outletCode = $outlet?->code ?: "OUT-{$locked->outlet_id}";
            $cateringRevAcc = "revenue:resto:{$outletCode}:catering";

            LedgerAccount::firstOrCreate(
                ['code' => $cateringRevAcc],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Pendapatan Katering Resto '.($outlet?->name ?? $outletCode),
                    'asset_code' => 'IDR',
                    'kind' => AccountKind::REVENUE->value,
                    'allow_negative' => false,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );

            $idempotencyKey = "resto:catering:hold:{$locked->id}:".Str::uuid();
            $holdAmount = Money::idr($locked->deposit_amount);

            $intent = $this->paymentGateway->hold(
                payable: $locked,
                amount: $holdAmount,
                idempotencyKey: $idempotencyKey,
                expiresAt: now()->addDays(30)
            );

            $locked->update([
                'user_id' => $customerUser->id,
                'deposit_intent_id' => $intent->id,
                'status' => CateringStatus::CONFIRMED,
            ]);

            return $locked;
        });
    }
}
