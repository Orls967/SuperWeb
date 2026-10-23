<?php

declare(strict_types=1);

namespace Modules\Party\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Party\Contracts\PartySanctionsInterface;

class PartySanctionsService implements PartySanctionsInterface
{
    public function getSanctionedPartyIds(?array $partyIds = null, array $statuses = ['hit', 'review']): array
    {
        $query = DB::table('pty_sanctions_checks')
            ->whereIn('status', $statuses);

        if ($partyIds !== null) {
            if (empty($partyIds)) {
                return [];
            }
            $query->whereIn('party_id', $partyIds);
        }

        return $query->pluck('party_id')->all();
    }
}
