<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FashionSourcingService (Fase 181 — Lini 28)
 *
 * Implements:
 *  - 181.2 Ethical labor audit & ESG approval gating before PO issuance to factory
 *  - 181.1 Exact size-color SKU allocation reconciliation (sum(sku) == total PO units)
 *  - 181.4 Markdown calendar approval (discounted price must respect minimum unit cost margin)
 */
class FashionSourcingService
{
    /**
     * Register garment factory and record ethical ESG audit status.
     */
    public function registerFactory(string $code, string $name, bool $laborApproved, bool $esgAuditPassed): object
    {
        DB::table('fsh_supplier_factories')->updateOrInsert(
            ['factory_code' => $code],
            [
                'factory_name' => $name,
                'labor_standard_approved' => $laborApproved,
                'esg_audit_passed' => $esgAuditPassed,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('fsh_supplier_factories')->where('factory_code', $code)->first();
    }

    /**
     * Issue purchase order with exact size-color SKU matrix allocation.
     * Enforces ESG ethical labor factory approval and SKU allocation conservation.
     */
    public function issuePurchaseOrder(string $factoryCode, string $collectionName, int $totalUnits, array $skuBreakdowns): object
    {
        $factory = DB::table('fsh_supplier_factories')->where('factory_code', $factoryCode)->first();
        if (! $factory) {
            throw new \InvalidArgumentException("Factory {$factoryCode} not found.");
        }

        // Ethical sourcing gate: Labor standards & ESG audit required
        if (! (bool) $factory->labor_standard_approved || ! (bool) $factory->esg_audit_passed) {
            throw new \RuntimeException("PO blocked: Factory {$factoryCode} fails ethical labor or ESG standards.");
        }

        // SKU allocation sum check
        $sumAllocated = array_sum(array_column($skuBreakdowns, 'units'));
        if ($sumAllocated !== $totalUnits) {
            throw new \RuntimeException("SKU allocation discrepancy: Sum of SKU breakdown units ({$sumAllocated}) does not equal total PO units ({$totalUnits}).");
        }

        $poCode = 'PO-FSH-'.strtoupper(Str::random(8));

        $id = DB::table('fsh_purchase_orders')->insertGetId([
            'po_code' => $poCode,
            'factory_code' => $factoryCode,
            'collection_name' => $collectionName,
            'total_order_units' => $totalUnits,
            'status' => 'ISSUED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($skuBreakdowns as $item) {
            DB::table('fsh_order_sku_breakdowns')->insert([
                'po_code' => $poCode,
                'sku_code' => $item['sku'],
                'color' => strtoupper($item['color']),
                'size' => strtoupper($item['size']),
                'units_allocated' => $item['units'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (object) DB::table('fsh_purchase_orders')->find($id);
    }

    /**
     * Plan promotional markdown.
     * Enforces minimum margin guardrail (discounted price must stay >= unit cost).
     */
    public function planMarkdown(string $collection, float $origPrice, float $discountPct, float $unitCost): object
    {
        $discountAmount = round($origPrice * ($discountPct / 100.0), 2);
        $discountedPrice = round($origPrice - $discountAmount, 2);

        // Margin guardrail: discounted price must cover unit cost
        $approved = ($discountedPrice >= $unitCost);

        $code = 'MKD-FSH-'.strtoupper(Str::random(8));

        $id = DB::table('fsh_markdown_plans')->insertGetId([
            'markdown_code' => $code,
            'collection_name' => $collection,
            'original_price_idr' => $origPrice,
            'discount_pct' => $discountPct,
            'discounted_price_idr' => $discountedPrice,
            'unit_cost_idr' => $unitCost,
            'margin_approved' => $approved,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('fsh_markdown_plans')->find($id);
    }

    /**
     * Quality audit gate (`fashion:audit`).
     */
    public function audit(): array
    {
        $unethicalPOs = DB::table('fsh_purchase_orders as p')
            ->join('fsh_supplier_factories as f', 'p.factory_code', '=', 'f.factory_code')
            ->where(function ($q) {
                $q->where('f.labor_standard_approved', false)
                    ->orWhere('f.esg_audit_passed', false);
            })
            ->count();

        return [
            'status' => $unethicalPOs === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_factories' => DB::table('fsh_supplier_factories')->count(),
            'total_purchase_orders' => DB::table('fsh_purchase_orders')->count(),
            'total_markdown_plans' => DB::table('fsh_markdown_plans')->count(),
            'discrepancy_count' => $unethicalPOs,
        ];
    }
}
