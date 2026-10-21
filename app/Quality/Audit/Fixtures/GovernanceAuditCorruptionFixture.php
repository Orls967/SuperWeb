<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Agri\Domain\Models\Ndvi\GovProposal;

final class GovernanceAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'governance:audit';
    }

    public function corrupt(): string
    {
        $proposal = GovProposal::firstOrCreate(
            ['proposal_code' => 'PROP-CORRUPT-01'],
            [
                'title' => 'Corrupt Test Proposal',
                'proposal_type' => 'EXPANSION_RESTO',
                'description' => 'Test corrupted voting weights',
                'action_payload' => ['target' => 'City-X'],
                'quorum_weight_required' => 10000,
                'total_yes_weight' => 5000,
                'total_no_weight' => 0,
                'total_abstain_weight' => 0,
                'status' => 'ACTIVE',
            ]
        );

        // Corrupt by mismatching recorded total_yes_weight vs votes (0 actual votes vs 999999 recorded)
        $proposal->total_yes_weight = 999999;
        $proposal->saveQuietly();

        return 'DAO Governance audit FAILED';
    }
}
