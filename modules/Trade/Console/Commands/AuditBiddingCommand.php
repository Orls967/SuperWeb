<?php

declare(strict_types=1);

namespace Modules\Trade\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 84.7 Audit AI Contract Bidding Agent: verify deterministic evaluation replay,
 * mandatory four-eyes approval on submissions, boundary guardrails, and contract encumbrance.
 */
class AuditBiddingCommand extends Command
{
    protected $signature = 'bidding:audit';

    protected $description = 'Audit AI Bidding Agent: verifikasi determinisme evaluasi lelang, four-eyes approval, dan komitmen kontrak (0 selisih)';

    public function handle(): int
    {
        $rows = [];
        $issues = 0;

        // 1. Reconstructibility of AI evaluations
        if (Schema::hasTable('trd_bid_runs') && Schema::hasTable('trd_bidding_agents')) {
            $runs = DB::table('trd_bid_runs as r')
                ->join('trd_bidding_agents as a', 'r.bidding_agent_id', '=', 'a.id')
                ->select(
                    'r.id',
                    'r.run_code',
                    'a.agent_code',
                    'r.tender_reference_code',
                    'r.commodity_feed_price_idr',
                    'r.estimated_logistics_cost_idr',
                    'r.optimal_bid_price_idr',
                    'r.clause_drafts',
                    'r.evaluation_hash'
                )
                ->get();

            $corruptedEvaluations = 0;
            foreach ($runs as $run) {
                $computedHash = hash(
                    'sha256',
                    "{$run->agent_code}:{$run->tender_reference_code}:{$run->commodity_feed_price_idr}:{$run->estimated_logistics_cost_idr}:{$run->optimal_bid_price_idr}:{$run->clause_drafts}"
                );

                if ($computedHash !== $run->evaluation_hash) {
                    $corruptedEvaluations++;
                }
            }

            if ($corruptedEvaluations > 0) {
                $rows[] = ['bidding.evaluation_hash_mismatch', '0', (string) $corruptedEvaluations, (string) $corruptedEvaluations, 'SELISIH'];
                $issues++;
            }
        }

        // 2. Submissions without four-eyes approval
        if (Schema::hasTable('trd_bid_submissions')) {
            $unapprovedSubmissions = DB::table('trd_bid_submissions')
                ->whereIn('status', ['SUBMITTED', 'WON'])
                ->where(function ($query) {
                    $query->whereNull('approved_by_user_id')
                        ->orWhereNull('approved_at');
                })
                ->count();

            if ($unapprovedSubmissions > 0) {
                $rows[] = ['bidding.unapproved_submission', '0', (string) $unapprovedSubmissions, (string) $unapprovedSubmissions, 'SELISIH'];
                $issues++;
            }

            // 3. Price boundary breaches
            $boundaryBreaches = DB::table('trd_bid_submissions as s')
                ->join('trd_bid_runs as r', 's.bid_run_id', '=', 'r.id')
                ->join('trd_bidding_agents as a', 'r.bidding_agent_id', '=', 'a.id')
                ->where(function ($query) {
                    $query->whereRaw('s.submitted_bid_price_idr < a.min_price_floor_idr')
                        ->orWhereRaw('s.submitted_bid_price_idr > a.max_price_ceiling_idr');
                })
                ->count();

            if ($boundaryBreaches > 0) {
                $rows[] = ['bidding.price_boundary_breach', '0', (string) $boundaryBreaches, (string) $boundaryBreaches, 'SELISIH'];
                $issues++;
            }

            // 4. Won tenders without contract code
            $wonWithoutContract = DB::table('trd_bid_submissions')
                ->where('status', 'WON')
                ->whereNull('created_contract_code')
                ->count();

            if ($wonWithoutContract > 0) {
                $rows[] = ['bidding.won_missing_contract_code', '0', (string) $wonWithoutContract, (string) $wonWithoutContract, 'SELISIH'];
                $issues++;
            }
        }

        $this->table(
            ['Sumber', 'Seharusnya', 'Aktual', 'Selisih', 'Status'],
            $rows === [] ? [['—', '0', '0', '0', 'OK']] : $rows
        );

        if ($issues > 0) {
            $this->error("Ditemukan {$issues} temuan audit AI bidding agent.");

            return self::FAILURE;
        }

        $this->info('✓ bidding:audit selesai: determinisme AI, four-eyes approval, guardrail harga, dan kontrak seimbang.');

        return self::SUCCESS;
    }
}
