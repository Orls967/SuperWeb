<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\LogisticsInvoice;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipperAccount;

class GenerateMonthlyInvoicesAction
{
    /**
     * Generate monthly invoices for postpaid B2B shippers idempotently.
     *
     * @return Collection<int, LogisticsInvoice>
     */
    public function execute(string $period): Collection
    {
        $createdInvoices = collect();

        $accounts = ShipperAccount::where('is_active', true)->get();

        foreach ($accounts as $account) {
            // Idempotency: skip if an invoice already exists for this shipper and billing period
            $existing = LogisticsInvoice::where('shipper_id', $account->shipper_id)
                ->where('billing_period', $period)
                ->where('kind', 'freight')
                ->first();

            if ($existing !== null) {
                continue;
            }

            // Find all un-invoiced postpaid shipments for this shipper
            $shipments = Shipment::where('shipper_id', $account->shipper_id)
                ->where('payment_terms', PaymentTerms::Postpaid)
                ->whereNull('invoice_id')
                ->whereNotIn('status', [ShipmentStatus::Draft, ShipmentStatus::Cancelled])
                ->get();

            if ($shipments->isEmpty()) {
                continue;
            }

            $totalAmount = (int) $shipments->sum('total_amount_idr');
            $periodClean = str_replace('-', '', $period);
            $invoiceNumber = sprintf('INV-LGX-%s-%04d', $periodClean, $account->shipper_id);
            $dueDate = now()->addDays($account->payment_terms_days)->toDateString();

            $invoice = DB::transaction(function () use (
                $account,
                $period,
                $invoiceNumber,
                $totalAmount,
                $dueDate,
                $shipments
            ) {
                $invoice = LogisticsInvoice::create([
                    'invoice_number' => $invoiceNumber,
                    'shipper_id' => $account->shipper_id,
                    'billing_period' => $period,
                    'total_amount_idr' => $totalAmount,
                    'paid_amount_idr' => 0,
                    'status' => 'unpaid',
                    'due_date' => $dueDate,
                ]);

                Shipment::whereIn('id', $shipments->pluck('id'))->update([
                    'invoice_id' => $invoice->id,
                ]);

                return $invoice;
            });

            $createdInvoices->push($invoice);
        }

        return $createdInvoices;
    }
}
