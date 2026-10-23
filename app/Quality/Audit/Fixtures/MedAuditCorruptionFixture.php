<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Med\Domain\Models\MedTalentContract;

final class MedAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'med:audit';
    }

    public function corrupt(): string
    {
        MedTalentContract::create([
            'id' => (string) Str::uuid(),
            'contract_code' => 'TC-CORRUPT-99',
            'project_id' => (string) Str::uuid(),
            'talent_party_id' => (string) Str::uuid(),
            'role_name' => 'ACTOR',
            'upfront_fee_minor' => 10_000_000,
            'backend_percentage' => 5.0,
            'calculated_royalty_minor' => -500_000,
            'payout_status' => 'HOLD',
        ]);

        return 'Media audit FAILED';
    }
}
