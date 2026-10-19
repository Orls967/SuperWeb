<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;

final class BiddingAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'bidding:audit';
    }

    public function corrupt(): string
    {
        // First ensure an agent and bid run exist
        $agentId = DB::table('trd_bidding_agents')->insertGetId([
            'agent_code' => 'AGT-CORRUPT-FIXTURE',
            'entity_code' => 'CORRUPT-ENT',
            'commodity_code' => 'COAL',
            'min_price_floor_idr' => 100_000_000,
            'max_price_ceiling_idr' => 500_000_000,
            'target_margin_pct' => 15.0,
            'max_risk_score' => 0.40,
            'status' => 'ACTIVE',
            'created_at' => '2026-10-19 12:00:00',
            'updated_at' => '2026-10-19 12:00:00',
        ]);

        $runId = DB::table('trd_bid_runs')->insertGetId([
            'run_code' => 'RUN-CORRUPT-FIXTURE',
            'bidding_agent_id' => $agentId,
            'tender_reference_code' => 'TND-CORRUPT-01',
            'commodity_feed_price_idr' => 150_000_000,
            'estimated_logistics_cost_idr' => 20_000_000,
            'buyer_risk_score' => 0.20,
            'optimal_bid_price_idr' => 195_500_000,
            'clause_drafts' => json_encode(['terms' => 'NET_30']),
            'evaluation_hash' => hash('sha256', 'AGT-CORRUPT-FIXTURE:TND-CORRUPT-01:150000000:20000000:195500000:{"terms":"NET_30"}'),
            'created_at' => '2026-10-19 12:00:00',
            'updated_at' => '2026-10-19 12:00:00',
        ]);

        // Insert unapproved submission with status SUBMITTED (violation)
        DB::table('trd_bid_submissions')->insert([
            'submission_code' => 'SUB-CORRUPT-FIXTURE',
            'bid_run_id' => $runId,
            'submitted_bid_price_idr' => 195_500_000,
            'requires_four_eyes' => true,
            'approved_by_user_id' => null, // violation: SUBMITTED without human approval
            'approved_at' => null,
            'status' => 'SUBMITTED',
            'created_at' => '2026-10-19 12:00:00',
            'updated_at' => '2026-10-19 12:00:00',
        ]);

        return 'bidding.unapproved_submission';
    }
}
