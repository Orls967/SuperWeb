<?php

declare(strict_types=1);

namespace Modules\Pricing\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 44.8 & 81.7 Audit pricing: price lock immutable, klaim promo ≤ anggaran,
 * override persetujuan, price list overlap, dokumen order quote freeze & guardrail (0 selisih).
 */
class AuditPricingCommand extends Command
{
    protected $signature = 'pricing:audit';

    protected $description = 'Audit pricing: lock harga, anggaran promo, override, overlap, dan guardrail quote (0 selisih)';

    public function handle(): int
    {
        $rows = [];
        $issues = 0;

        // 1. Klaim promo melebihi anggaran.
        $overBudget = DB::table('pric_promotions')
            ->whereRaw('spent_idr > budget_idr')
            ->count();
        if ($overBudget > 0) {
            $rows[] = ['promo.over_budget', '0', (string) $overBudget, (string) $overBudget, 'SELISIH'];
            $issues++;
        }

        // 2. Klaim settled tanpa approval (harus melalui approval).
        $settledNoApproval = DB::table('pric_promotion_claims')
            ->where('status', 'settled')
            ->whereNull('approval_id')
            ->count();
        if ($settledNoApproval > 0) {
            $rows[] = ['promo.settled_no_approval', '0', (string) $settledNoApproval, (string) $settledNoApproval, 'SELISIH'];
            $issues++;
        }

        // 3. Override approved tanpa waktu keputusan (konsistensi status).
        $badOverride = DB::table('pric_price_overrides')
            ->where('status', 'approved')
            ->whereNull('decided_at')
            ->count();
        if ($badOverride > 0) {
            $rows[] = ['override.no_decided_at', '0', (string) $badOverride, (string) $badOverride, 'SELISIH'];
            $issues++;
        }

        // 4. Price lock: applied > list tanpa alasan override = kebocoran.
        $leak = DB::table('pric_price_locks')
            ->whereRaw('applied_price_idr > list_price_idr')
            ->whereNull('reason')
            ->count();
        if ($leak > 0) {
            $rows[] = ['price_lock.leak', '0', (string) $leak, (string) $leak, 'SELISIH'];
            $issues++;
        }

        // 5. Price list aktif overlap pada channel+segmen+wilayah sama.
        $overlap = DB::table('pric_price_lists as a')
            ->join('pric_price_lists as b', function ($join) {
                $join->on('a.channel', '=', 'b.channel')
                    ->on('a.segment', '=', 'b.segment')
                    ->on('a.region_code', '=', 'b.region_code')
                    ->whereColumn('a.id', '<>', 'b.id');
            })
            ->where('a.status', 'active')
            ->where('b.status', 'active')
            ->whereRaw("a.valid_from <= COALESCE(b.valid_until, '9999-12-31')")
            ->whereRaw("b.valid_from <= COALESCE(a.valid_until, '9999-12-31')")
            ->distinct()
            ->count();
        if ($overlap > 0) {
            $rows[] = ['price_list.overlap', '0', (string) $overlap, (string) $overlap, 'SELISIH'];
            $issues++;
        }

        // 6. Converted quote tanpa referensi dokumen order (Fase 81.4 / 81.7).
        $unlinkedConvertedQuotes = DB::table('pric_frozen_quotes')
            ->where('status', 'CONVERTED')
            ->where(function ($query) {
                $query->whereNull('order_ref')->orWhere('order_ref', '');
            })
            ->count();
        if ($unlinkedConvertedQuotes > 0) {
            $rows[] = ['quote.converted_no_order_ref', '0', (string) $unlinkedConvertedQuotes, (string) $unlinkedConvertedQuotes, 'SELISIH'];
            $issues++;
        }

        // 7. Frozen quote melanggar batas guardrail floor/ceiling (Fase 81.3 / 81.7).
        $guardrailBreaches = DB::table('pric_frozen_quotes')
            ->where(function ($query) {
                $query->whereColumn('frozen_price_idr', '<', 'floor_price_idr')
                    ->orWhereColumn('frozen_price_idr', '>', 'ceiling_price_idr');
            })
            ->count();
        if ($guardrailBreaches > 0) {
            $rows[] = ['quote.guardrail_breach', '0', (string) $guardrailBreaches, (string) $guardrailBreaches, 'SELISIH'];
            $issues++;
        }

        $this->table(
            ['Sumber', 'Seharusnya', 'Aktual', 'Selisih', 'Status'],
            $rows === [] ? [['—', '0', '0', '0', 'OK']] : $rows
        );

        if ($issues > 0) {
            $this->error("Ditemukan {$issues} temuan pricing.");

            return self::FAILURE;
        }

        $this->info('✓ pricing:audit selesai: lock harga, anggaran promo, override, overlap, dan guardrail quote konsisten.');

        return self::SUCCESS;
    }
}
