<?php

declare(strict_types=1);

namespace Modules\Governance\Contracts;

use Illuminate\Support\Collection;

interface ProposalDirectory
{
    /**
     * @return Collection<int, mixed>
     */
    public function getRecentProposals(int $limit = 10): Collection;
}
