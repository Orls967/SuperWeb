<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Models\ContainerDwell;
use Modules\Logistics\Domain\Models\LogisticsInvoice;
use Modules\Logistics\Domain\Models\ShipperAccount;

class GenerateDdInvoicesAction
{
    /**
     * Terbitkan invoice D&D (kind=dd) per shipper untuk hitungan yang sudah ditutup dan belum ditagih.
     * Pembayaran memakai PayLogisticsInvoiceAction yang mengkredit piutang shipper (diakrual lebih dulu).
     *
     * @return Collection<int, LogisticsInvoice>
     */
    public function execute(): Collection
    {
        $created = collect();

        $groups = ContainerDwell::where('status', ContainerDwell::STATUS_CLOSED)
            ->whereNull('invoice_id')
            ->where('accrued_amount_idr', '>', 0)
            ->get()
            ->groupBy('shipper_id');

        foreach ($groups as $shipperId => $dwells) {
            $created->push(DB::transaction(function () use ($shipperId, $dwells) {
                $locked = ContainerDwell::whereIn('id', $dwells->pluck('id'))->whereNull('invoice_id')->lockForUpdate()->get();
                if ($locked->isEmpty()) {
                    return null;
                }

                $terms = ShipperAccount::where('shipper_id', $shipperId)->value('payment_terms_days') ?? 14;
                $sequence = LogisticsInvoice::where('shipper_id', $shipperId)->where('kind', 'dd')->count() + 1;

                $invoice = LogisticsInvoice::create([
                    'invoice_number' => sprintf('INV-DD-%s-%04d-%d', now()->format('Ym'), $shipperId, $sequence),
                    'kind' => 'dd',
                    'shipper_id' => $shipperId,
                    'billing_period' => now()->format('Y-m'),
                    'total_amount_idr' => (int) $locked->sum('accrued_amount_idr'),
                    'paid_amount_idr' => 0,
                    'status' => 'unpaid',
                    'due_date' => now()->addDays((int) $terms)->toDateString(),
                ]);

                ContainerDwell::whereIn('id', $locked->pluck('id'))->update(['invoice_id' => $invoice->id]);

                return $invoice;
            }));
        }

        return $created->filter()->values();
    }
}
