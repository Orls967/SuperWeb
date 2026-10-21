<?php

declare(strict_types=1);

namespace Modules\Agri\Console\Commands;

use Illuminate\Console\Command;
use Modules\Agri\Domain\Models\Ndvi\GovProposal;
use Modules\Agri\Domain\Models\Ndvi\GovVote;

class AuditGovernanceCommand extends Command
{
    protected $signature = 'governance:audit';

    protected $description = 'Audit DAO governance proposals, voter weights, and hash-chained vote receipts';

    public function handle(): int
    {
        $this->info('Starting DAO Corporate Governance Audit (governance:audit)...');

        $discrepancies = [];
        $proposals = GovProposal::with('votes')->get();

        foreach ($proposals as $proposal) {
            $sumYes = (int) $proposal->votes->where('vote_choice', 'YES')->sum('weight_cast');
            $sumNo = (int) $proposal->votes->where('vote_choice', 'NO')->sum('weight_cast');
            $sumAbstain = (int) $proposal->votes->where('vote_choice', 'ABSTAIN')->sum('weight_cast');

            if ($sumYes !== (int) $proposal->total_yes_weight) {
                $discrepancies[] = "Proposal {$proposal->proposal_code} YES weight mismatch: votes={$sumYes}, record={$proposal->total_yes_weight}";
            }
            if ($sumNo !== (int) $proposal->total_no_weight) {
                $discrepancies[] = "Proposal {$proposal->proposal_code} NO weight mismatch: votes={$sumNo}, record={$proposal->total_no_weight}";
            }
            if ($sumAbstain !== (int) $proposal->total_abstain_weight) {
                $discrepancies[] = "Proposal {$proposal->proposal_code} ABSTAIN weight mismatch: votes={$sumAbstain}, record={$proposal->total_abstain_weight}";
            }

            // Verify hash chain
            $prevHash = str_repeat('0', 64);
            $orderedVotes = $proposal->votes->sortBy('id');
            foreach ($orderedVotes as $vote) {
                if ($vote->previous_vote_hash !== $prevHash) {
                    $discrepancies[] = "Vote #{$vote->id} on {$proposal->proposal_code} has broken previous hash link!";
                }

                $expectedReceipt = hash('sha256', "{$proposal->proposal_code}:{$vote->voter_user_id}:{$vote->vote_choice}:{$vote->weight_cast}:{$vote->previous_vote_hash}");
                if ($vote->vote_receipt_hash !== $expectedReceipt) {
                    $discrepancies[] = "Vote #{$vote->id} receipt hash mismatch: recorded={$vote->vote_receipt_hash}, expected={$expectedReceipt}";
                }

                $prevHash = $vote->vote_receipt_hash;
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Proposals', $proposals->count()],
                ['Total Votes Cast', GovVote::count()],
                ['Discrepancies', count($discrepancies)],
            ]
        );

        if (! empty($discrepancies)) {
            $this->error('DAO Governance audit FAILED with '.count($discrepancies).' discrepancies detected!');
            foreach ($discrepancies as $d) {
                $this->error(" - {$d}");
            }

            return self::FAILURE;
        }

        $this->info('DAO Governance audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
