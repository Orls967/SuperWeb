<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Models\Carrier;
use Modules\Logistics\Domain\Models\CarrierPayment;
use Modules\Logistics\Domain\Models\ShipmentLeg;

class PayCarriersAction
{
    public function __construct(
        private readonly LogisticsLedger $ledger
    ) {}

    /**
     * Alur 10: bayar utang carrier atas leg yang sudah diakrual dan melewati termin pembayarannya.
     * Debit utang carrier, kredit kliring bank. Satu pembayaran per carrier per eksekusi.
     */
    public function execute(Carrier $carrier, ?int $paidBy = null): ?CarrierPayment
    {
        return DB::transaction(function () use ($carrier, $paidBy) {
            $legs = ShipmentLeg::where('carrier_id', $carrier->id)
                ->whereNotNull('cost_accrued_at')
                ->whereNull('carrier_payment_id')
                ->where('cost_accrued_at', '<=', now()->subDays($carrier->payment_terms_days))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($legs->isEmpty()) {
                return null;
            }

            $amount = (int) $legs->sum('carrier_cost_idr');

            $this->ledger->post(
                type: TransactionType::LOGISTICS_CARRIER_PAYMENT,
                description: "Pembayaran carrier {$carrier->name} ({$legs->count()} leg)",
                idempotencyKey: "lgx:carrier_pay:{$carrier->id}:".md5($legs->pluck('id')->implode(',')),
                entries: [
                    [LogisticsLedger::carrierPayableCode($carrier->id), -$amount],
                    [LogisticsLedger::BANK_CLEARING, $amount],
                ],
                referenceType: Carrier::class,
                referenceId: $carrier->id,
                createdBy: $paidBy,
            );

            $payment = CarrierPayment::create([
                'carrier_id' => $carrier->id,
                'amount_idr' => $amount,
                'leg_count' => $legs->count(),
                'week_key' => now()->format('o-\WW'),
                'paid_by' => $paidBy,
                'paid_at' => now(),
            ]);

            ShipmentLeg::whereIn('id', $legs->pluck('id'))->update(['carrier_payment_id' => $payment->id]);

            return $payment;
        });
    }
}
