<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Enums\SessionStatus;
use Modules\Resto\Domain\Enums\TableStatus;
use Modules\Resto\Domain\Exceptions\InvalidOrderOperationException;
use Modules\Resto\Domain\Models\Order;

class VoidOrderAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly PaymentGateway $paymentGateway
    ) {}

    public function handle(Order $order, User $actor, string $reason): Order
    {
        // Authorization: only outlet_manager or admin
        $role = $actor->role ?? '';
        if (! in_array($role, ['admin', 'outlet_manager'], true)) {
            throw new InvalidOrderOperationException('Hanya Outlet Manager atau Admin yang berhak melakukan void pesanan.');
        }

        if (trim($reason) === '') {
            throw new InvalidOrderOperationException('Alasan void pesanan wajib diisi.');
        }

        return DB::transaction(function () use ($order, $actor, $reason) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status === OrderStatus::VOID) {
                return $lockedOrder;
            }

            $wasPaid = $lockedOrder->status === OrderStatus::PAID;
            $outlet = $lockedOrder->outlet;
            $outletCode = $outlet?->code ?: "OUT-{$lockedOrder->outlet_id}";

            if ($wasPaid) {
                if ($lockedOrder->payment_method === 'wallet') {
                    $intent = PaymentIntent::where('payable_type', 'resto_order')
                        ->where('payable_id', $lockedOrder->id)
                        ->latest('id')
                        ->first();

                    if ($intent) {
                        $this->paymentGateway->refund(
                            intent: $intent,
                            refundAmount: $lockedOrder->payableAmount(),
                            reason: "Void pesanan #{$lockedOrder->number}: {$reason}",
                            idempotencyKey: "resto:order:refund:{$lockedOrder->id}:".Str::uuid()
                        );
                    }
                } elseif ($lockedOrder->payment_method === 'cash') {
                    // Reverse cash receipt in ledger
                    $cashAccCode = "cash:drawer:{$outletCode}:IDR";
                    $splits = $lockedOrder->revenueSplits();

                    $entries = [
                        PostingEntryDTO::forCode($cashAccCode, 'IDR', BigDecimal::of($lockedOrder->grand_total)),
                    ];

                    foreach ($splits as $accountCode => $splitMoney) {
                        if ($splitMoney->isPositive()) {
                            $entries[] = PostingEntryDTO::forCode($accountCode, $splitMoney->assetCode, $splitMoney->amount->negated());
                        }
                    }

                    $this->ledger->post(new PostingDTO(
                        type: TransactionType::REFUND->value,
                        description: "Pembatalan/Refund tunai pesanan {$lockedOrder->number}. Alasan: {$reason}",
                        idempotencyKey: "resto:order:void:cash:{$lockedOrder->id}:".Str::uuid(),
                        entries: $entries,
                        referenceType: 'resto_order',
                        referenceId: $lockedOrder->id,
                        createdBy: $actor->id
                    ));
                }
            }

            $lockedOrder->status = OrderStatus::VOID;
            $lockedOrder->voided_by = $actor->id;
            $lockedOrder->void_reason = $reason;
            $lockedOrder->save();

            // Release session/table if not yet closed
            if ($lockedOrder->table_session_id) {
                $session = $lockedOrder->session;
                if ($session && $session->status !== SessionStatus::CLOSED) {
                    $session->status = SessionStatus::ABANDONED;
                    $session->closed_at = now();
                    $session->save();

                    $table = $session->table;
                    if ($table) {
                        $table->status = TableStatus::AVAILABLE;
                        $table->save();
                    }
                }
            }

            return $lockedOrder->load(['items', 'session.table', 'voidedBy']);
        });
    }
}
