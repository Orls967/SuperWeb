<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Mall\Application\Services\TenantSalesService;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\OvertimeStatus;
use Modules\Mall\Domain\Enums\RentModel;
use Modules\Mall\Domain\Enums\UtilityType;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\InvoiceLine;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\OvertimeRequest;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\TenantSalesReport;
use Modules\Mall\Domain\Models\UtilityReading;

class GenerateMonthlyInvoicesAction
{
    public function __construct(
        protected TenantSalesService $salesService,
    ) {}

    /**
     * Generate invoice bulanan untuk sebuah lease tertentu secara idempoten.
     */
    public function generateForLease(Lease $lease, string $periodMonth): Invoice
    {
        return DB::transaction(function () use ($lease, $periodMonth) {
            // Kunci lease sebelum memeriksa invoice agar eksekusi paralel terserialisasi
            $lease = Lease::query()->lockForUpdate()->findOrFail($lease->getKey());
            $existing = Invoice::query()
                ->where('lease_id', $lease->id)
                ->where('period_month', $periodMonth)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && in_array($existing->status, [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::PAID], true)) {
                return $existing;
            }
            // Sinkronisasi data penjualan jika ada provider terintegrasi
            $salesReport = $this->salesService->syncMonthlySales($lease, $periodMonth)
                ?? TenantSalesReport::where('lease_id', $lease->id)
                    ->where('period_month', $periodMonth)
                    ->first();

            $linesData = [];

            // 1. Sewa Pokok & Bagi Hasil
            $currentYear = $lease->currentLeaseYear();
            $baseRent = $lease->calculateMonthlyRent($currentYear);
            $netSales = $salesReport ? (int) $salesReport->net_sales : 0;
            $revSharePercent = (float) ($lease->revenue_share_percent ?? 0);
            $revShareAmount = (int) round($netSales * ($revSharePercent / 100.0));

            if ($lease->rent_model === RentModel::FIXED) {
                $linesData[] = [
                    'type' => InvoiceLineType::BASE_RENT,
                    'description' => "Sewa Pokok Unit {$lease->unit?->unit_number} (Tahun ke-{$currentYear})",
                    'quantity' => 1.0,
                    'unit_price' => $baseRent,
                    'amount' => $baseRent,
                ];
            } elseif ($lease->rent_model === RentModel::REVENUE_SHARE) {
                $linesData[] = [
                    'type' => InvoiceLineType::BASE_RENT,
                    'description' => "Bagi Hasil Omzet {$revSharePercent}% Unit {$lease->unit?->unit_number} (Omzet: Rp ".number_format($netSales, 0, ',', '.').')',
                    'quantity' => 1.0,
                    'unit_price' => $revShareAmount,
                    'amount' => $revShareAmount,
                ];
            } elseif ($lease->rent_model === RentModel::GREATER_OF) {
                $linesData[] = [
                    'type' => InvoiceLineType::BASE_RENT,
                    'description' => "Sewa Pokok Minimum Unit {$lease->unit?->unit_number} (Tahun ke-{$currentYear})",
                    'quantity' => 1.0,
                    'unit_price' => $baseRent,
                    'amount' => $baseRent,
                ];

                if ($revShareAmount > $baseRent) {
                    $topUp = $revShareAmount - $baseRent;
                    $linesData[] = [
                        'type' => InvoiceLineType::REVENUE_SHARE_TOPUP,
                        'description' => "Top-Up Bagi Hasil Omzet ({$revSharePercent}% Omzet Rp ".number_format($netSales, 0, ',', '.').' = Rp '.number_format($revShareAmount, 0, ',', '.').' vs Min Rp '.number_format($baseRent, 0, ',', '.').')',
                        'quantity' => 1.0,
                        'unit_price' => $topUp,
                        'amount' => $topUp,
                    ];
                }
            }

            // 2. Service Charge
            if ($lease->service_charge_monthly > 0) {
                $linesData[] = [
                    'type' => InvoiceLineType::SERVICE_CHARGE,
                    'description' => "Biaya Pengelolaan & Service Charge Unit {$lease->unit?->unit_number}",
                    'quantity' => 1.0,
                    'unit_price' => $lease->service_charge_monthly,
                    'amount' => $lease->service_charge_monthly,
                ];
            }

            // 3. Utilitas Listrik
            $elecReading = UtilityReading::where('lease_id', $lease->id)
                ->where('period_month', $periodMonth)
                ->where('utility_type', UtilityType::ELECTRICITY)
                ->first();

            if ($elecReading && $elecReading->amount > 0) {
                $linesData[] = [
                    'type' => InvoiceLineType::ELECTRICITY,
                    'description' => "Pemakaian Listrik Periode {$periodMonth} ({$elecReading->usage} kWh)",
                    'quantity' => (float) $elecReading->usage,
                    'unit_price' => (int) round($elecReading->amount / max(1.0, (float) $elecReading->usage)),
                    'amount' => (int) $elecReading->amount,
                ];
            }

            // 4. Utilitas Air
            $waterReading = UtilityReading::where('lease_id', $lease->id)
                ->where('period_month', $periodMonth)
                ->where('utility_type', UtilityType::WATER)
                ->first();

            if ($waterReading && $waterReading->amount > 0) {
                $linesData[] = [
                    'type' => InvoiceLineType::WATER,
                    'description' => "Pemakaian Air Bersih Periode {$periodMonth} ({$waterReading->usage} m³)",
                    'quantity' => (float) $waterReading->usage,
                    'unit_price' => (int) round($waterReading->amount / max(1.0, (float) $waterReading->usage)),
                    'amount' => (int) $waterReading->amount,
                ];
            }

            // 5. Lembur AC
            $overtimeRequests = OvertimeRequest::where('lease_id', $lease->id)
                ->where('status', OvertimeStatus::APPROVED)
                ->whereNull('invoice_id')
                ->where('date', 'like', "{$periodMonth}%")
                ->get();

            $totalOvertimeCost = 0;
            $totalOvertimeHours = 0.0;
            foreach ($overtimeRequests as $ot) {
                $totalOvertimeCost += (int) $ot->total_cost;
                $totalOvertimeHours += (float) $ot->hours;
            }

            if ($totalOvertimeCost > 0) {
                $linesData[] = [
                    'type' => InvoiceLineType::AC_OVERTIME,
                    'description' => "Biaya Lembur AC Operasional ({$totalOvertimeHours} Jam)",
                    'quantity' => $totalOvertimeHours,
                    'unit_price' => (int) round($totalOvertimeCost / max(1.0, $totalOvertimeHours)),
                    'amount' => $totalOvertimeCost,
                ];
            }

            // 6. Validasi parkir pelanggan yang ditanggung tenant pada periode ini
            $validatedSessions = ParkingSession::query()
                ->unbilledValidation($lease->tenant_id, $periodMonth)
                ->get();

            $parkingValidationTotal = (int) $validatedSessions->sum('discount_amount');

            if ($parkingValidationTotal > 0) {
                $linesData[] = [
                    'type' => InvoiceLineType::PARKING_VALIDATION,
                    'description' => 'Validasi Parkir Pelanggan ('.$validatedSessions->count().' tiket ditanggung tenant)',
                    'quantity' => (float) $validatedSessions->count(),
                    'unit_price' => (int) round($parkingValidationTotal / max(1, $validatedSessions->count())),
                    'amount' => $parkingValidationTotal,
                ];
            }

            $subtotal = array_sum(array_column($linesData, 'amount'));

            // Tanggal jatuh tempo: billing_day + grace_days
            $billingDay = max(1, min(28, (int) $lease->billing_day));
            $dueDate = Carbon::createFromFormat('Y-m', $periodMonth)
                ->startOfMonth()
                ->setDay($billingDay)
                ->addDays($lease->grace_days);

            $cleanPeriod = str_replace('-', '', $periodMonth);
            $invoiceNumber = $existing?->invoice_number ?? sprintf('INV-MALL-%s-%04d', $cleanPeriod, $lease->id);

            $invoice = Invoice::updateOrCreate(
                [
                    'lease_id' => $lease->id,
                    'period_month' => $periodMonth,
                ],
                [
                    'invoice_number' => $invoiceNumber,
                    'tenant_id' => $lease->tenant_id,
                    'property_id' => $lease->property_id,
                    'subtotal' => $subtotal,
                    'penalty_amount' => 0,
                    'total_amount' => $subtotal,
                    'paid_amount' => 0,
                    'status' => InvoiceStatus::ISSUED,
                    'due_date' => $dueDate,
                    'issued_at' => now(),
                ]
            );

            // Bersihkan baris lama jika ada
            $invoice->lines()->delete();

            foreach ($linesData as $item) {
                InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'type' => $item['type'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'amount' => $item['amount'],
                    'paid_amount' => 0,
                    'status' => 'unpaid',
                ]);
            }

            // Tautkan overtime requests ke invoice
            foreach ($overtimeRequests as $ot) {
                $ot->update([
                    'invoice_id' => $invoice->id,
                    'status' => OvertimeStatus::BILLED,
                ]);
            }

            // Tandai sesi parkir yang validasinya sudah ditagihkan agar tidak dobel
            if ($validatedSessions->isNotEmpty()) {
                ParkingSession::whereIn('id', $validatedSessions->pluck('id'))
                    ->update(['validation_invoice_id' => $invoice->id]);
            }

            return $invoice;
        });
    }

    /**
     * Generate invoice bulanan untuk semua lease yang aktif di sistem atau di properti tertentu.
     *
     * @return array<Invoice>
     */
    public function generateAll(string $periodMonth, ?int $propertyId = null): array
    {
        $query = Lease::where('status', LeaseStatus::ACTIVE);
        if ($propertyId !== null) {
            $query->where('property_id', $propertyId);
        }

        $leases = $query->get();
        $invoices = [];

        foreach ($leases as $lease) {
            $invoices[] = $this->generateForLease($lease, $periodMonth);
        }

        return $invoices;
    }
}
