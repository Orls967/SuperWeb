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
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Mall\Contracts\LoyaltyLedger;
use Modules\Mall\Contracts\ParkingValidator;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Enums\SessionStatus;
use Modules\Resto\Domain\Enums\ShiftStatus;
use Modules\Resto\Domain\Enums\TableStatus;
use Modules\Resto\Domain\Exceptions\InvalidOrderOperationException;
use Modules\Resto\Domain\Exceptions\NoActiveShiftException;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\Shift;

class PayOrderAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly PaymentGateway $paymentGateway,
        private readonly VerifiesWalletPin $verifyPinAction,
        private readonly ?ParkingValidator $parkingValidator = null,
        private readonly ?LoyaltyLedger $loyaltyLedger = null
    ) {}

    public function handle(
        Order $order,
        string $paymentMethod = 'cash',
        ?int $cashTendered = null,
        ?string $pin = null,
        ?string $idempotencyKey = null,
        ?int $shiftId = null,
        ?int $cashierUserId = null,
        ?User $payerUser = null,
        ?string $parkingTicketNumber = null,
        ?string $mallVoucherCode = null,
        ?int $redeemPoints = null
    ): Order {
        $idemKey = $idempotencyKey ?: $order->idempotency_key ?: "order_pay_{$order->id}_".Str::uuid();

        return DB::transaction(function () use (
            $order,
            $paymentMethod,
            $cashTendered,
            $pin,
            $idemKey,
            $shiftId,
            $cashierUserId,
            $payerUser,
            $parkingTicketNumber,
            $mallVoucherCode,
            $redeemPoints
        ) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            // Idempotency: if already paid, return
            if ($lockedOrder->status === OrderStatus::PAID) {
                return $lockedOrder->load(['items', 'session.table']);
            }

            if ($lockedOrder->status === OrderStatus::VOID) {
                throw new InvalidOrderOperationException("Pesanan #{$lockedOrder->id} sudah dibatalkan (VOID).");
            }

            $outlet = $lockedOrder->outlet;
            $outletCode = $outlet?->code ?: "OUT-{$lockedOrder->outlet_id}";

            // 1. Terapkan Voucher Mall bila ada
            if ($mallVoucherCode && $this->loyaltyLedger) {
                $voucherDiscount = $this->loyaltyLedger->applyVoucher(
                    voucherCode: $mallVoucherCode,
                    tenantExternalRef: $outletCode,
                    spendAmount: (int) $lockedOrder->grand_total,
                    transactionRef: $lockedOrder->number
                );
                $lockedOrder->mall_voucher_code = $mallVoucherCode;
                $lockedOrder->mall_voucher_discount = $voucherDiscount;
                $lockedOrder->discount += $voucherDiscount;
                $lockedOrder->grand_total = max(0, $lockedOrder->grand_total - $voucherDiscount);
                $lockedOrder->save();
            }

            // 2. Tukar Duta Points untuk potongan harga bila diminta
            if ($redeemPoints && $this->loyaltyLedger) {
                $payer = $payerUser ?? ($lockedOrder->customer_id ? User::find($lockedOrder->customer_id) : auth()->user());
                if ($payer) {
                    $ptsDiscount = $this->loyaltyLedger->redeemPointsForDiscount(
                        user: $payer,
                        pointsToRedeem: $redeemPoints,
                        description: "Diskon Duta Points pesanan #{$lockedOrder->number}",
                        referenceId: (string) $lockedOrder->id
                    );
                    $lockedOrder->discount += $ptsDiscount;
                    $lockedOrder->grand_total = max(0, $lockedOrder->grand_total - $ptsDiscount);
                    $lockedOrder->save();
                }
            }

            if ($paymentMethod === 'cash') {
                // Find active shift
                $shift = null;
                if ($shiftId) {
                    $shift = Shift::where('id', $shiftId)->where('status', ShiftStatus::OPEN)->first();
                }
                if ($shift === null && $cashierUserId) {
                    $shift = Shift::where('cashier_id', $cashierUserId)
                        ->where('outlet_id', $lockedOrder->outlet_id)
                        ->where('status', ShiftStatus::OPEN)
                        ->first();
                }
                if ($shift === null) {
                    $shift = Shift::where('outlet_id', $lockedOrder->outlet_id)
                        ->where('status', ShiftStatus::OPEN)
                        ->latest('opened_at')
                        ->first();
                }

                if ($shift === null) {
                    throw new NoActiveShiftException('Kasir harus membuka shift aktif sebelum menerima pembayaran tunai.');
                }

                $tendered = $cashTendered ?? $lockedOrder->grand_total;
                if ($tendered < $lockedOrder->grand_total) {
                    throw new InvalidOrderOperationException(
                        'Uang tunai diterima (Rp '.number_format($tendered, 0, ',', '.').
                        ') kurang dari total tagihan (Rp '.number_format($lockedOrder->grand_total, 0, ',', '.').').'
                    );
                }

                // Post cash receipt to ledger
                $cashAccCode = "cash:drawer:{$outletCode}:IDR";
                $this->ensureLedgerAccountExists($cashAccCode, "Kas Fisik Kasir {$outlet?->name}", AccountKind::CASH);

                $splits = $lockedOrder->revenueSplits();
                $entries = [
                    PostingEntryDTO::forCode($cashAccCode, 'IDR', BigDecimal::of($lockedOrder->grand_total)->negated()),
                ];

                foreach ($splits as $accountCode => $splitMoney) {
                    if ($splitMoney->isPositive()) {
                        $this->ensureLedgerAccountExists($accountCode, "Pendapatan {$outlet?->name}", AccountKind::REVENUE);
                        $entries[] = PostingEntryDTO::forCode($accountCode, $splitMoney->assetCode, $splitMoney->amount);
                    }
                }

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::PAYMENT->value,
                    description: "Pembayaran tunai pesanan {$lockedOrder->number}",
                    idempotencyKey: "resto:order:pay:cash:{$lockedOrder->id}:{$idemKey}",
                    entries: $entries,
                    referenceType: 'resto_order',
                    referenceId: $lockedOrder->id,
                    createdBy: $cashierUserId ?? $shift->cashier_id
                ));

                $lockedOrder->status = OrderStatus::PAID;
                $lockedOrder->paid_at = now();
                $lockedOrder->payment_method = 'cash';
                $lockedOrder->shift_id = $shift->id;
                $lockedOrder->save();
            } elseif ($paymentMethod === 'wallet') {
                $payer = $payerUser ?? ($lockedOrder->customer_id ? User::find($lockedOrder->customer_id) : auth()->user());
                if ($payer === null) {
                    throw new InvalidOrderOperationException('Pengguna pembayar tidak ditemukan untuk pembayaran wallet.');
                }

                if ($pin !== null) {
                    $this->verifyPinAction->execute($payer, $pin);
                }

                // Set customer_id if not present
                if (! $lockedOrder->customer_id) {
                    $lockedOrder->customer_id = $payer->id;
                    $lockedOrder->save();
                }

                // Charge via PaymentHub
                $intent = $this->paymentGateway->charge($lockedOrder, "resto:order:wallet:{$lockedOrder->id}:{$idemKey}");

                $lockedOrder->refresh();
                $lockedOrder->payment_method = 'wallet';
                if ($shiftId) {
                    $lockedOrder->shift_id = $shiftId;
                }
                $lockedOrder->save();
            } elseif ($paymentMethod === 'points') {
                $payer = $payerUser ?? ($lockedOrder->customer_id ? User::find($lockedOrder->customer_id) : auth()->user());
                if ($payer && $this->loyaltyLedger && $lockedOrder->grand_total > 0) {
                    $ptsNeeded = (int) ceil($lockedOrder->grand_total / 100);
                    $this->loyaltyLedger->redeemPointsForDiscount(
                        user: $payer,
                        pointsToRedeem: $ptsNeeded,
                        description: "Pelunasan penuh Duta Points pesanan #{$lockedOrder->number}",
                        referenceId: (string) $lockedOrder->id
                    );
                }

                $lockedOrder->status = OrderStatus::PAID;
                $lockedOrder->paid_at = now();
                $lockedOrder->payment_method = 'points';
                if ($shiftId) {
                    $lockedOrder->shift_id = $shiftId;
                }
                $lockedOrder->save();
            } else {
                // Other methods (e.g. split, voucher) handled with appropriate tag
                $lockedOrder->status = OrderStatus::PAID;
                $lockedOrder->paid_at = now();
                $lockedOrder->payment_method = $paymentMethod;
                if ($shiftId) {
                    $lockedOrder->shift_id = $shiftId;
                }
                $lockedOrder->save();
            }

            // 3. Validasi Tiket Parkir Pelanggan bila disertakan
            if ($parkingTicketNumber && $this->parkingValidator) {
                $spendForParking = (int) ($lockedOrder->subtotal > 0 ? $lockedOrder->subtotal : $lockedOrder->grand_total);
                $valResult = $this->parkingValidator->validateTicket(
                    ticketNumber: $parkingTicketNumber,
                    tenantExternalRef: $outletCode,
                    spendAmount: $spendForParking
                );
                $lockedOrder->parking_ticket_number = $valResult->ticketNumber;
                $lockedOrder->parking_validation_hours = $valResult->freeHours;
                $lockedOrder->save();
            }

            // 4. Perolehan Duta Points untuk Pembeli
            $payerForPoints = $payerUser ?? ($lockedOrder->customer_id ? User::find($lockedOrder->customer_id) : null);
            if ($payerForPoints && $this->loyaltyLedger && $lockedOrder->grand_total >= 10_000) {
                $earned = $this->loyaltyLedger->awardPoints(
                    user: $payerForPoints,
                    spendAmount: (int) $lockedOrder->grand_total,
                    receiptNumber: $lockedOrder->number,
                    tenantExternalRef: $outletCode,
                    processor: $cashierUserId ? User::find($cashierUserId) : null
                );
                $lockedOrder->loyalty_points_earned = $earned;
                $lockedOrder->save();
            }

            // Close session & release table
            if ($lockedOrder->table_session_id) {
                $session = $lockedOrder->session;
                if ($session) {
                    $session->status = SessionStatus::CLOSED;
                    $session->closed_at = now();
                    $session->save();

                    $table = $session->table;
                    if ($table) {
                        $table->status = TableStatus::AVAILABLE;
                        $table->save();
                    }
                }
            }

            return $lockedOrder->load(['items.menuItem', 'items.tray', 'session.table', 'shift']);
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
