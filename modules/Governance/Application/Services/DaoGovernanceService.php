<?php

declare(strict_types=1);

namespace Modules\Governance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Epc\Contracts\ProjectDraftFactory;
use Modules\Governance\Domain\Models\GovProposal;
use Modules\Governance\Domain\Models\GovVote;
use Modules\Governance\Domain\Models\GovVoterWeight;
use Modules\Hcm\Contracts\EmployeeDirectory;
use Modules\Rwa\Contracts\VotingWeightReader;
use Modules\Shared\Domain\Algorithms\Hash\MerkleCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Hash\MerkleTree;
use RuntimeException;

final class DaoGovernanceService
{
    public function __construct(
        protected MerkleTree $merkleTree,
        protected MerkleCertificateVerifier $merkleVerifier,
        protected ?VotingWeightReader $votingWeightReader = null,
        protected ?ProjectDraftFactory $projectDraftFactory = null,
        protected ?EmployeeDirectory $employeeDirectory = null,
        protected ?ClockInterface $clock = null,
    ) {
        $this->votingWeightReader = $votingWeightReader ?? app(VotingWeightReader::class);
        $this->projectDraftFactory = $projectDraftFactory ?? app(ProjectDraftFactory::class);
        $this->employeeDirectory = $employeeDirectory ?? app(EmployeeDirectory::class);
    }

    /**
     * D86-4: Snapshot voter weights & generate deterministic Merkle root.
     * Hak suara bersumber dari:
     * 1. Karyawan aktif HCM (bobot 100 per karyawan).
     * 2. Pemegang token RWA via K9 VotingWeightReader.
     * 3. Rekaman bobot snapshot manual / mitra di gov_voter_weights.
     *
     * @param  string  $assetCode  e.g. "RWA-AGRI-01"
     * @return array{root: string, leaves: list<string>, voter_count: int, total_weight: int}
     */
    public function snapshotVoterWeights(string $assetCode = 'RWA-PROP-01', ?CarbonImmutable $snapshotAt = null): array
    {
        $now = $snapshotAt ?? CarbonImmutable::now();

        // 1. Snapshot RWA holders
        $rwaHolders = [];
        try {
            if ($this->votingWeightReader) {
                $rwaHolders = $this->votingWeightReader->tokenUnitsByHolderAt($assetCode, $now);
            }
        } catch (\Throwable) {
            $rwaHolders = [];
        }

        // 2. Snapshot Active Employees via EmployeeDirectory contract
        $employeeWeights = [];
        try {
            if ($this->employeeDirectory) {
                $employees = $this->employeeDirectory->listActiveEmployees();
                foreach ($employees as $emp) {
                    $uId = (int) ($emp['user_id'] ?: $emp['id']);
                    $employeeWeights[$uId] = 100; // 100 units base weight for active employees
                }
            }
        } catch (\Throwable) {
            $employeeWeights = [];
        }

        // 3. Existing gov_voter_weights
        $recordedWeights = GovVoterWeight::all();
        $manualWeights = [];
        foreach ($recordedWeights as $rw) {
            $manualWeights[(int) $rw->voter_user_id] = (int) $rw->voting_weight;
        }

        // Aggregate per voter_id (id menaik untuk determinisme PR3)
        $allVoterIds = array_unique(array_merge(
            array_keys($rwaHolders),
            array_keys($employeeWeights),
            array_keys($manualWeights)
        ));
        sort($allVoterIds, SORT_NUMERIC);

        $leaves = [];
        $totalWeight = 0;

        foreach ($allVoterIds as $voterId) {
            $weight = ($rwaHolders[$voterId] ?? 0)
                + ($employeeWeights[$voterId] ?? 0)
                + ($manualWeights[$voterId] ?? 0);

            if ($weight > 0) {
                $leaves[] = "{$voterId}:{$weight}";
                $totalWeight += $weight;

                // Sync ke gov_voter_weights
                GovVoterWeight::updateOrCreate(
                    ['voter_user_id' => $voterId],
                    [
                        'voter_role' => isset($rwaHolders[$voterId]) ? 'RWA_HOLDER' : 'HCM_EMPLOYEE',
                        'voting_weight' => $weight,
                        'passport_hash' => hash('sha256', "passport:{$voterId}:{$weight}"),
                    ]
                );
            }
        }

        $treeResult = $this->merkleTree->build($leaves);

        return [
            'root' => $treeResult->root,
            'leaves' => $leaves,
            'voter_count' => count($leaves),
            'total_weight' => $totalWeight,
        ];
    }

    /**
     * D86-4 & D86-5: Cast vote with Merkle proof and append-only hash chain.
     */
    public function castVote(
        GovProposal $proposal,
        int $voterUserId,
        int $weight,
        string $choice,
        ?array $merkleProof = null
    ): GovVote {
        if (! in_array($choice, ['YES', 'NO', 'ABSTAIN'], true)) {
            throw new RuntimeException("Pilihan vote tidak valid: {$choice}");
        }

        return DB::transaction(function () use ($proposal, $voterUserId, $weight, $choice, $merkleProof) {
            $lockedProposal = GovProposal::where('id', $proposal->id)->lockForUpdate()->firstOrFail();

            if ($lockedProposal->status !== 'ACTIVE') {
                throw new RuntimeException('Proposal tidak dalam status ACTIVE');
            }

            // Validasi jendela voting jika ada
            if ($lockedProposal->voting_ends_at && now()->isAfter($lockedProposal->voting_ends_at)) {
                throw new RuntimeException('Voting setelah waktu penutupan ditolak');
            }

            // Validasi anti double-voting
            $existing = GovVote::where('proposal_id', $lockedProposal->id)
                ->where('voter_user_id', $voterUserId)
                ->first();

            if ($existing) {
                throw new RuntimeException('Duplicate vote rejected: pemilih sudah memberikan suara');
            }

            // Verifikasi Merkle proof terhadap snapshot proposal jika root tersedia
            if ($lockedProposal->merkle_root) {
                $leaf = "{$voterUserId}:{$weight}";
                if ($merkleProof === null) {
                    throw new RuntimeException('Merkle proof wajib disertakan untuk proposal ber-snapshot');
                }
                $valid = $this->merkleVerifier->verify($leaf, $merkleProof, $lockedProposal->merkle_root);
                if (! $valid) {
                    throw new RuntimeException('Merkle proof tidak cocok dengan snapshot proposal');
                }
            }

            // Rantai hash (append-only)
            $lastVote = GovVote::where('proposal_id', $lockedProposal->id)->latest('id')->first();
            $prevHash = $lastVote ? $lastVote->vote_receipt_hash : str_repeat('0', 64);

            $receiptHash = hash('sha256', "{$lockedProposal->proposal_code}:{$voterUserId}:{$choice}:{$weight}:{$prevHash}");

            $vote = GovVote::create([
                'proposal_id' => $lockedProposal->id,
                'voter_user_id' => $voterUserId,
                'vote_choice' => $choice,
                'weight_cast' => $weight,
                'previous_vote_hash' => $prevHash,
                'vote_receipt_hash' => $receiptHash,
                'merkle_proof' => $merkleProof,
            ]);

            // Update proposal tally
            if ($choice === 'YES') {
                $lockedProposal->increment('total_yes_weight', $weight);
            } elseif ($choice === 'NO') {
                $lockedProposal->increment('total_no_weight', $weight);
            } else {
                $lockedProposal->increment('total_abstain_weight', $weight);
            }

            return $vote;
        });
    }

    /**
     * D86-5: Finalize proposal & generate EPC project draft via K10.
     * TETAP status 'draft_pending_approval' (dilarang eksekusi final otomatis tanpa approval dewan).
     */
    public function finalizeAndExecuteProposal(GovProposal $proposal): GovProposal
    {
        return DB::transaction(function () use ($proposal) {
            $locked = GovProposal::where('id', $proposal->id)->lockForUpdate()->firstOrFail();

            $totalTurnout = $locked->total_yes_weight + $locked->total_no_weight + $locked->total_abstain_weight;

            if ($totalTurnout < $locked->quorum_weight_required) {
                $locked->update(['status' => 'REJECTED']);

                return $locked;
            }

            if ($locked->total_yes_weight > $locked->total_no_weight) {
                $execRef = 'PRJ-AUTO-'.strtoupper(substr(hash('sha256', "{$locked->proposal_code}:auto"), 0, 12));

                $budgetIdr = 1_000_000_000;
                if (isset($locked->action_payload['budget_idr'])) {
                    $budgetIdr = (int) $locked->action_payload['budget_idr'];
                }

                // Panggil K10 ProjectDraftFactory untuk draf proyek EPC
                $epcProjectId = null;
                if ($this->projectDraftFactory) {
                    $idempotencyKey = "GOV-EXEC-{$locked->proposal_code}";
                    $epcProjectId = $this->projectDraftFactory->draftFromProposal(
                        (int) $locked->id,
                        (string) $locked->title,
                        $budgetIdr,
                        $idempotencyKey
                    );
                }

                $locked->update([
                    'status' => 'APPROVED',
                    'execution_reference_code' => $execRef,
                    'epc_project_id' => $epcProjectId,
                ]);
            } else {
                $locked->update(['status' => 'REJECTED']);
            }

            return $locked;
        });
    }
}
