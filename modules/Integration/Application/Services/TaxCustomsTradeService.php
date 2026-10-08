<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * TaxCustomsTradeService (Fase 208)
 *
 * Implements:
 *  - 208.1 Consolidated indirect tax engine with gapless e-faktur sequence
 *  - 208.4 Trade-based money laundering guard detecting invoice / customs declaration mismatch
 */
class TaxCustomsTradeService
{
    /**
     * Issue tax invoice with gapless sequence and rate calculation.
     */
    public function issueTaxInvoice(string $jurisdiction, string $taxType, float $baseAmount, float $ratePercent): object
    {
        return DB::transaction(function () use ($jurisdiction, $taxType, $baseAmount, $ratePercent) {
            $lastSeq = DB::table('erm_tax_compliance_records')->lockForUpdate()->max('tax_invoice_seq') ?? 0;
            $nextSeq = (int) $lastSeq + 1;
            $efaktur = 'FAKTUR-'.strtoupper($jurisdiction).'-'.date('Y').'-'.str_pad((string) $nextSeq, 7, '0', STR_PAD_LEFT);
            $taxAmount = round($baseAmount * ($ratePercent / 100.0), 2);

            $id = DB::table('erm_tax_compliance_records')->insertGetId([
                'tax_invoice_seq' => $nextSeq,
                'efaktur_number' => $efaktur,
                'jurisdiction' => strtoupper($jurisdiction),
                'tax_type' => strtoupper($taxType),
                'taxable_base_amount_idr' => $baseAmount,
                'tax_rate_percent' => $ratePercent,
                'calculated_tax_amount_idr' => $taxAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (object) DB::table('erm_tax_compliance_records')->find($id);
        });
    }

    /**
     * Inspect cross-border trade invoice against declared customs valuation to guard against TBML.
     */
    public function inspectTradeTransaction(string $tradeCode, string $invoiceNo, float $customsValue, float $invoiceValue): object
    {
        $diff = abs($invoiceValue - $customsValue);
        $variancePercent = $customsValue > 0 ? round(($diff / $customsValue) * 100, 2) : 0.0;

        // Tolerance max 10%. Anything higher flagged and placed on hold
        $flagged = ($variancePercent > 10.0);
        $status = $flagged ? 'HELD_FOR_REVIEW' : 'CLEARED';

        DB::table('erm_tbml_trade_guards')->updateOrInsert(
            ['trade_code' => strtoupper($tradeCode)],
            [
                'invoice_number' => $invoiceNo,
                'customs_declared_value_idr' => $customsValue,
                'invoice_value_idr' => $invoiceValue,
                'variance_percent' => $variancePercent,
                'is_flagged_for_review' => $flagged,
                'status' => $status,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('erm_tbml_trade_guards')->where('trade_code', strtoupper($tradeCode))->first();
    }

    /**
     * Quality audit gate (`trade:audit`).
     */
    public function audit(): array
    {
        $unresolvedTbmlHolds = DB::table('erm_tbml_trade_guards')
            ->where('status', 'HELD_FOR_REVIEW')
            ->count();

        return [
            'status' => $unresolvedTbmlHolds === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_tax_records' => DB::table('erm_tax_compliance_records')->count(),
            'total_trade_checks' => DB::table('erm_tbml_trade_guards')->count(),
            'discrepancy_count' => $unresolvedTbmlHolds,
        ];
    }
}
