<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Modules\Egy\Domain\Models\UtilityInvoiceLine;

class ConsolidatedUtilityBillingService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function configureTouWindow(string $start, string $end): void
    {
        $startParts = explode(':', $start);
        $endParts = explode(':', $end);

        $sm = (int) ($startParts[1] ?? -1);
        $em = (int) ($endParts[1] ?? -1);

        if ($sm % 15 !== 0 || $em % 15 !== 0) {
            throw new InvalidArgumentException('Jendela tarif TOU harus kelipatan 15 menit.');
        }
    }

    public function isPeakHour(CarbonImmutable $time): bool
    {
        $local = $time->setTimezone('Asia/Jakarta');
        $totalMin = ((int) $local->format('G')) * 60 + ((int) $local->format('i'));

        // WBP is 17:00 (1020 min) up to strictly before 22:00 (1320 min)
        return $totalMin >= 1020 && $totalMin < 1320;
    }

    public function calculateCharge(int $wh, int $ratePerKwh): int
    {
        return (int) round(($wh * $ratePerKwh) / 1000.0, 0, PHP_ROUND_HALF_UP);
    }

    public function generateMonthlyInvoice(string $propertyId, string $billingPeriod): UtilityConsolidatedInvoice
    {
        $existing = UtilityConsolidatedInvoice::where('property_id', $propertyId)
            ->where('billing_period', $billingPeriod)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($propertyId, $billingPeriod) {
            $meters = SmartMeter::where('consumer_property_id', $propertyId)->get();
            $meterIds = $meters->pluck('id');

            $start = CarbonImmutable::parse($billingPeriod.'-01 00:00:00', 'Asia/Jakarta')->startOfMonth();
            $end = $start->endOfMonth();

            $readings = MeterReading::whereIn('meter_id', $meterIds)
                ->whereBetween('reading_at', [$start, $end])
                ->get();

            $wbpWh = 0;
            $lwbpWh = 0;

            foreach ($readings as $r) {
                if ($r->is_wbp) {
                    $wbpWh += (int) $r->delta_wh;
                } else {
                    $lwbpWh += (int) $r->delta_wh;
                }
            }

            $wbpRate = 2100;
            $lwbpRate = 1450;
            $wbpCharge = $this->calculateCharge($wbpWh, $wbpRate);
            $lwbpCharge = $this->calculateCharge($lwbpWh, $lwbpRate);

            $totalWh = $wbpWh + $lwbpWh;
            $totalElec = $wbpCharge + $lwbpCharge;

            $invoiceNumber = 'INV-UTIL-'.str_replace('-', '', $billingPeriod).'-'.$propertyId;

            $invoice = UtilityConsolidatedInvoice::create([
                'id' => (string) Str::uuid(),
                'invoice_number' => $invoiceNumber,
                'property_id' => $propertyId,
                'billing_period' => $billingPeriod,
                'total_wh' => $totalWh,
                'electricity_charge_minor' => $totalElec,
                'water_charge_minor' => 0,
                'district_cooling_charge_minor' => 0,
                'gas_charge_minor' => 0,
                'total_consolidated_minor' => $totalElec,
                'status' => 'ISSUED',
            ]);

            if ($wbpWh > 0) {
                UtilityInvoiceLine::create([
                    'id' => (string) Str::uuid(),
                    'invoice_id' => $invoice->id,
                    'window_type' => 'WBP',
                    'wh_consumed' => $wbpWh,
                    'rate_per_kwh_minor' => $wbpRate,
                    'subtotal_minor' => $wbpCharge,
                    'line_total_minor' => $wbpCharge,
                ]);
            }

            if ($lwbpWh > 0 || $wbpWh === 0) {
                UtilityInvoiceLine::create([
                    'id' => (string) Str::uuid(),
                    'invoice_id' => $invoice->id,
                    'window_type' => 'LWBP',
                    'wh_consumed' => $lwbpWh,
                    'rate_per_kwh_minor' => $lwbpRate,
                    'subtotal_minor' => $lwbpCharge,
                    'line_total_minor' => $lwbpCharge,
                ]);
            }

            return $invoice;
        });
    }

    public function createDraftInvoice(string $propertyId, string $billingPeriod): UtilityConsolidatedInvoice
    {
        $existing = UtilityConsolidatedInvoice::where('property_id', $propertyId)
            ->where('billing_period', $billingPeriod)
            ->first();

        if ($existing) {
            return $existing;
        }

        $invoice = $this->generateMonthlyInvoice($propertyId, $billingPeriod);
        $invoice->update(['status' => 'DRAFT']);

        return $invoice;
    }

    public function issueInvoice(string $invoiceId): UtilityConsolidatedInvoice
    {
        $invoice = UtilityConsolidatedInvoice::findOrFail($invoiceId);

        $propertyArCode = "ar:egy:property:{$invoice->property_id}:IDR";
        LedgerAccount::firstOrCreate(
            ['code' => $propertyArCode],
            [
                'name' => "AR Energy Property {$invoice->property_id}",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );

        $revCode = 'egy:utility_revenue:IDR';
        LedgerAccount::firstOrCreate(
            ['code' => $revCode],
            [
                'name' => 'Utility Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ]
        );

        if ($invoice->total_consolidated_minor > 0) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'ENERGY_CONSOLIDATED_UTILITY',
                description: "Utility consolidated invoice {$invoice->invoice_number}",
                idempotencyKey: "egy:invoice:{$invoice->invoice_number}",
                entries: Posting::lines()
                    ->debit($propertyArCode, $invoice->total_consolidated_minor)
                    ->credit($revCode, $invoice->total_consolidated_minor)
                    ->toEntries('IDR'),
                referenceType: 'EGY_UTILITY_INVOICE',
                referenceId: $invoice->invoice_number,
            ));

            $invoice->update([
                'ledger_transaction_id' => (string) $tx->uuid,
                'status' => 'ISSUED',
            ]);
        } else {
            $invoice->update(['status' => 'ISSUED']);
        }

        return $invoice;
    }

    public function settleInvoice(string $invoiceId, string $idempotencyKey): UtilityConsolidatedInvoice
    {
        $invoice = UtilityConsolidatedInvoice::findOrFail($invoiceId);

        $propertyArCode = "ar:egy:property:{$invoice->property_id}:IDR";
        $clearingCode = 'clearing:external:IDR';

        LedgerAccount::firstOrCreate(
            ['code' => $propertyArCode],
            [
                'name' => "AR Energy Property {$invoice->property_id}",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );

        if ($invoice->total_consolidated_minor > 0) {
            $this->ledgerService->post(new PostingDTO(
                type: 'ENERGY_METER_READING_BILL',
                description: "Settlement for invoice {$invoice->invoice_number}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit($clearingCode, $invoice->total_consolidated_minor)
                    ->credit($propertyArCode, $invoice->total_consolidated_minor)
                    ->toEntries('IDR'),
                referenceType: 'EGY_UTILITY_INVOICE',
                referenceId: $invoice->invoice_number,
            ));
        }

        $invoice->update(['status' => 'SETTLED']);

        return $invoice;
    }
}
