<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HtlTravelPass;

final class IssueGlobalTravelPassAction
{
    public function execute(
        string $userReference,
        string $initialTier = 'SILVER',
        ?array $metadata = null
    ): HtlTravelPass {
        $passNumber = 'TP-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        $userId = is_numeric($userReference) ? (int) $userReference : 1;

        return HtlTravelPass::create([
            'id' => (string) Str::uuid(),
            'pass_number' => $passNumber,
            'user_id' => $userId,
            'user_reference' => $userReference,
            'tier' => strtoupper($initialTier),
            'cumulative_nights' => 0,
            'cumulative_spend_idr' => 0,
            'pts_balance' => 0,
            'is_active' => true,
            'metadata' => $metadata ?? ['enrolled_channel' => 'PORTAL', 'marketing_opt_in' => true],
        ]);
    }
}
