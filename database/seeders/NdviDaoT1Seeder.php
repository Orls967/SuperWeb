<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Agri\Application\Services\Ndvi\PrecisionAgriAndDaoService;
use Modules\Agri\Domain\Models\Ndvi\AgriFinancingInstallment;
use Modules\Governance\Application\Services\DaoGovernanceService;
use Modules\Governance\Domain\Models\GovProposal;
use Modules\Governance\Domain\Models\GovVoterWeight;
use Modules\Shared\Domain\Algorithms\Hash\MerkleTree;

final class NdviDaoT1Seeder extends Seeder
{
    public function run(): void
    {
        $agriService = app(PrecisionAgriAndDaoService::class);
        $govService = app(DaoGovernanceService::class);
        $merkleAlgorithm = app(MerkleTree::class);

        // 1. Seed 100 Farmer plots & financing installments
        for ($i = 1; $i <= 100; $i++) {
            $plotCode = sprintf('PLT-T1-%03d', $i);
            $farmerId = 2000 + $i;

            // Half healthy (>= 0.650), half stressed (< 0.650)
            $isHealthy = ($i % 2 === 1);
            $pixels = $isHealthy
                ? [['nir' => 5000, 'red' => 1000], ['nir' => 6000, 'red' => 1500], ['nir' => 4500, 'red' => 1500]] // 53/90 ~ 0.5889 or higher
                : [['nir' => 3000, 'red' => 2000], ['nir' => 2500, 'red' => 2000], ['nir' => 4000, 'red' => 3000]];

            $agriService->recordSatelliteScan(
                plotCode: $plotCode,
                scanDate: now()->subDays(2),
                ndviScore: $pixels
            );

            AgriFinancingInstallment::firstOrCreate(
                ['installment_code' => sprintf('INST-T1-%03d', $i)],
                [
                    'contract_farming_code' => sprintf('CTR-T1-%03d', $i),
                    'plot_code' => $plotCode,
                    'farmer_party_id' => $farmerId,
                    'tranche_number' => 1,
                    'amount_idr' => 10_000_000,
                    'required_min_ndvi' => 0.450,
                    'status' => 'PENDING_EVALUATION',
                ]
            );
        }

        // 2. Seed 20 Governance proposals with voter snapshot
        for ($v = 1; $v <= 10; $v++) {
            GovVoterWeight::firstOrCreate(
                ['voter_user_id' => 3000 + $v],
                [
                    'voter_role' => 'STRATEGIC_PARTNER',
                    'voting_weight' => 5000 * $v,
                    'passport_hash' => hash('sha256', "passport:partner:{$v}"),
                ]
            );
        }

        $snapshot = $govService->snapshotVoterWeights();
        $merkleRoot = $snapshot['root'];

        for ($p = 1; $p <= 20; $p++) {
            $code = sprintf('PROP-T1-%02d', $p);
            $proposal = GovProposal::firstOrCreate(
                ['proposal_code' => $code],
                [
                    'title' => "Proposal T1 Expansion #{$p}",
                    'proposal_type' => 'STRATEGIC_EXPANSION',
                    'description' => "Expansion project #{$p} for agricultural logistics.",
                    'action_payload' => ['budget_idr' => 500_000_000, 'city' => 'Malang'],
                    'quorum_weight_required' => 20_000,
                    'merkle_root' => $merkleRoot,
                    'voting_starts_at' => now()->subDay(),
                    'voting_ends_at' => now()->addDays(5),
                    'status' => 'ACTIVE',
                ]
            );

            // Cast vote from voter #1
            $voterId = 3001;
            $voterWeight = 5000;
            // Generate proof for voter #1 (leaf 3001:5000)
            $leafStr = "{$voterId}:{$voterWeight}";
            $leafIndex = array_search($leafStr, $snapshot['leaves'], true);
            if ($leafIndex !== false) {
                $treeResult = $merkleAlgorithm->build($snapshot['leaves']);
                $proof = $merkleAlgorithm->getProof($treeResult, (int) $leafIndex);

                $govService->castVote(
                    proposal: $proposal,
                    voterUserId: $voterId,
                    weight: $voterWeight,
                    choice: 'YES',
                    merkleProof: $proof
                );
            }
        }
    }
}
