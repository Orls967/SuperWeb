<?php

declare(strict_types=1);

namespace Modules\Party\Contracts;

interface PartySanctionsInterface
{
    /**
     * @param  array<int, string>|null  $partyIds
     * @param  list<string>  $statuses
     * @return array<int, string>
     */
    public function getSanctionedPartyIds(?array $partyIds = null, array $statuses = ['hit', 'review']): array;
}
