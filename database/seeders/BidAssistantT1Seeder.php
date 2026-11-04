<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BidAssistantT1Seeder extends Seeder
{
    /**
     * D84 Simulasi T1: 50 tender, 500 evaluasi penawaran deterministik, 100 kontrak klausul K13.
     */
    public function run(): void
    {
        $now = '2026-11-04 00:00:00';

        // 1. 100 template klausul K13
        $clauses = [];
        for ($k = 1; $k <= 100; $k++) {
            $clauses[] = [
                'code' => sprintf('CTR-T1-K13-%03d', $k),
                'title' => sprintf('Klausul Komersial Tender Standar #%03d', $k),
                'category' => 'COMMERCIAL_DEFAULT',
                'body_template' => sprintf('Pasal %d: Ketentuan penyerahan dan pembayaran lelang komoditas industri.', $k),
                'version' => 1,
                'is_active' => true,
                'dispute_count' => $k % 5,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('ctr_clause_templates')->insertOrIgnore($clauses);

        // 2. 10 agen lelang aktif
        $agentIds = [];
        for ($a = 1; $a <= 10; $a++) {
            $agentCode = sprintf('AGT-T1-%02d', $a);
            $id = DB::table('trd_bidding_agents')->insertGetId([
                'agent_code' => $agentCode,
                'entity_code' => 'TRD-GLOBAL-INDONESIA',
                'commodity_code' => 'THERMAL_COAL_GAR5000',
                'min_price_floor_idr' => 800_000_000,
                'max_price_ceiling_idr' => 1_800_000_000,
                'target_margin_pct' => 15.0,
                'max_risk_score' => 0.40,
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $agentIds[$a] = ['id' => $id, 'code' => $agentCode];
        }

        // 3. 500 evaluasi pada 50 tender (10 evaluasi per tender)
        $runs = [];
        for ($i = 1; $i <= 500; $i++) {
            $agentIdx = (($i - 1) % 10) + 1;
            $tenderIdx = (($i - 1) % 50) + 1;
            $agent = $agentIds[$agentIdx];
            $tenderCode = sprintf('TND-T1-2026-%03d', $tenderIdx);
            $feedPrice = 850_000_000 + (($i % 20) * 5_000_000);
            $logCost = 50_000_000 + (($i % 5) * 2_000_000);
            $optimalBid = (int) round(($feedPrice + $logCost) * 1.15);
            $clauseJson = json_encode([
                'payment_terms' => 'NET_30_LC',
                'delivery_incoterm' => 'FOB_JAKARTA',
                'templates' => ['CTR-T1-K13-001', 'CTR-T1-K13-002'],
            ]);
            $evalHash = hash(
                'sha256',
                "{$agent['code']}:{$tenderCode}:{$feedPrice}:{$logCost}:{$optimalBid}:{$clauseJson}"
            );

            $runs[] = [
                'run_code' => sprintf('RUN-T1-%04d', $i),
                'bidding_agent_id' => $agent['id'],
                'tender_reference_code' => $tenderCode,
                'commodity_feed_price_idr' => $feedPrice,
                'estimated_logistics_cost_idr' => $logCost,
                'buyer_risk_score' => 0.20,
                'optimal_bid_price_idr' => $optimalBid,
                'clause_drafts' => $clauseJson,
                'evaluation_hash' => $evalHash,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($runs, 250) as $chunk) {
            DB::table('trd_bid_runs')->insert($chunk);
        }
    }
}

