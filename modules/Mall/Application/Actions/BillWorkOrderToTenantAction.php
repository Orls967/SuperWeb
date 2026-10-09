<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\InvoiceLine;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\WorkOrder;

class BillWorkOrderToTenantAction
{
    /**
     * Tagihkan biaya perbaikan work order ke tagihan invoice tenant.
     *
     * @throws InvalidArgumentException
     */
    public function execute(WorkOrder $workOrder): Invoice
    {
        return DB::transaction(function () use ($workOrder) {
            // Kunci work order agar pemeriksaan "sudah ditagih" dan penerbitan invoice atomik
            $lockedWorkOrder = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->getKey());

            if (! $lockedWorkOrder->tenant_id) {
                throw new InvalidArgumentException('Work order tidak memiliki asosiasi tenant yang dituju.');
            }

            if ($lockedWorkOrder->billed_invoice_id) {
                throw new InvalidArgumentException("Work order sudah pernah ditagihkan ke Invoice #{$lockedWorkOrder->billed_invoice_id}.");
            }

            if ($lockedWorkOrder->total_cost <= 0) {
                throw new InvalidArgumentException('Total biaya perbaikan harus lebih dari 0 untuk dapat ditagihkan.');
            }

            $tenant = Tenant::findOrFail($lockedWorkOrder->tenant_id);

            // Cari invoice terbuka milik tenant, atau buat invoice baru jika belum ada
            $invoice = Invoice::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', [
                    InvoiceStatus::DRAFT,
                    InvoiceStatus::ISSUED,
                    InvoiceStatus::OVERDUE,
                    InvoiceStatus::PARTIALLY_PAID,
                ])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                $lease = $tenant->leases()->where('status', LeaseStatus::ACTIVE)->first();
                $period = now()->format('Y-m');
                $invoiceNumber = 'INV-'.str_replace('-', '', $period).'-'.strtoupper(Str::random(6));

                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'lease_id' => $lease?->id,
                    'tenant_id' => $tenant->id,
                    'property_id' => $lockedWorkOrder->property_id ?? $lease?->property_id,
                    'period_month' => $period,
                    'subtotal' => 0,
                    'penalty_amount' => 0,
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'status' => InvoiceStatus::ISSUED,
                    'due_date' => now()->addDays(14)->toDateString(),
                    'issued_at' => now(),
                ]);
            }

            // Tambahkan baris tagihan biaya perbaikan
            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'type' => InvoiceLineType::REPAIR_COST,
                'description' => "Biaya Perbaikan Fasilitas: {$lockedWorkOrder->title} ({$lockedWorkOrder->order_number})",
                'quantity' => 1,
                'unit_price' => $lockedWorkOrder->total_cost,
                'amount' => $lockedWorkOrder->total_cost,
                'paid_amount' => 0,
                'status' => 'unpaid',
            ]);

            $invoice->subtotal += $lockedWorkOrder->total_cost;
            $invoice->total_amount += $lockedWorkOrder->total_cost;
            $invoice->save();

            $lockedWorkOrder->update([
                'is_billable_to_tenant' => true,
                'billed_invoice_id' => $invoice->id,
            ]);

            return $invoice->fresh(['lines', 'tenant']);
        });
    }
}
