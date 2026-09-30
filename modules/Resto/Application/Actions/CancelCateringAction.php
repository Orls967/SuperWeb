<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Resto\Domain\Enums\CateringStatus;
use Modules\Resto\Domain\Models\CateringOrder;
use Modules\Shared\Domain\ValueObjects\Money;

class CancelCateringAction
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway
    ) {}

    public function handle(
        CateringOrder $cateringOrder,
        string $reason
    ): CateringOrder {
        return DB::transaction(function () use ($cateringOrder, $reason) {
            $locked = CateringOrder::where('id', $cateringOrder->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === CateringStatus::COMPLETED || $locked->status === CateringStatus::CANCELLED) {
                return $locked;
            }

            $depositIntent = $locked->depositIntent;

            // Hitung sisa hari menuju hari H acara
            $eventDate = Carbon::parse($locked->event_date)->startOfDay();
            $now = Carbon::now()->startOfDay();
            $daysUntilEvent = $now->diffInDays($eventDate, false);

            if ($depositIntent && $depositIntent->status->value === 'held') {
                if ($daysUntilEvent >= 3) {
                    // Batal H-3 atau lebih lama: deposit dikembalikan penuh (release hold dari escrow)
                    $this->paymentGateway->release(
                        intent: $depositIntent,
                        idempotencyKey: "resto:catering:release:{$locked->id}:".Str::uuid()
                    );
                } else {
                    // Batal < 3 hari: deposit hangus & diakui sebagai pendapatan denda pembatalan katering
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

                    $this->paymentGateway->capture(
                        intent: $depositIntent,
                        finalAmount: Money::idr($locked->deposit_amount),
                        idempotencyKey: "resto:catering:forfeit:{$locked->id}:".Str::uuid()
                    );
                }
            }

            $locked->update([
                'status' => CateringStatus::CANCELLED,
                'cancellation_reason' => $reason,
            ]);

            return $locked;
        });
    }
}
