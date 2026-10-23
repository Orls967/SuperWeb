<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreAiDecisionSnapshot;

final class AiAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ai:audit';
    }

    public function corrupt(): string
    {
        CoreAiDecisionSnapshot::create([
            'id' => (string) Str::uuid(),
            'decision_code' => 'DEC-CORRUPT-'.Str::upper(Str::random(8)),
            'model_name' => 'DYNAMIC_PRICING',
            'model_version' => '1.0.0',
            'seed' => '99999',
            'input_snapshot' => [
                'item_code' => 'CORRUPTED-ITEM',
                'base_price_idr' => 10000,
                'floor_price_idr' => 7000,
                'ceiling_price_idr' => 15000,
            ],
            'decision_output' => [
                'final_price_idr' => 11000,
            ],
            'decision_hash' => 'tampered_invalid_sha256_hash_value',
        ]);

        return 'AI audit FAILED: Decision hash mismatch';
    }
}
