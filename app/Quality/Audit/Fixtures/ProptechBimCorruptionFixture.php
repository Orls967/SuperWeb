<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Proptech\Domain\Models\Bim\BimModel;
use Modules\Proptech\Domain\Models\Bim\TwinComponent;

final class ProptechBimCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'prp:audit-bim';
    }

    public function corrupt(): string
    {
        $model = BimModel::firstOrCreate(
            ['project_code' => 'PRJ-CORRUPT-BIM', 'version' => 1],
            [
                'model_code' => 'BIM-PRJ-CORRUPT-V1',
                'name' => 'Corrupt Project Model V1',
                'previous_hash' => str_repeat('0', 64),
                'current_hash' => hash('sha256', 'valid-hash-v1'),
                'metadata' => ['floors' => 5],
                'status' => 'APPROVED',
            ]
        );

        // Inject corrupted TwinComponent with completion_pct > 100% violating physical progress bounds
        $component = TwinComponent::create([
            'bim_model_id' => $model->id,
            'component_code' => 'CMP-CORRUPT-WBS-999',
            'wbs_node_code' => 'WBS-EPC-CORRUPT-01',
            'category' => 'HVAC_CHILLER',
            'name' => 'Corrupted Progress Chiller Unit',
            'location_spec' => 'Roof Mechanical Deck',
            'status' => 'INSTALLED',
            'completion_pct' => 150, // Physical progress > 100% is invalid
        ]);

        return 'CMP-CORRUPT-WBS-999';
    }
}
