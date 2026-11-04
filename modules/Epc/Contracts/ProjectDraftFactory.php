<?php

declare(strict_types=1);

namespace Modules\Epc\Contracts;

interface ProjectDraftFactory
{
    /**
     * Create an EPC project draft from an approved governance proposal.
     * The resulting project must have status 'draft_pending_approval'.
     *
     * @return string EPC project UUID
     */
    public function draftFromProposal(
        int $governanceProposalId,
        string $title,
        int $budgetIdr,
        string $idempotencyKey
    ): string;
}
