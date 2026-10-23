<?php

declare(strict_types=1);

namespace Modules\Proptech\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Proptech\Domain\Models\Bim\BimModel;
use Modules\Proptech\Domain\Models\Bim\TwinComponent;
use Modules\Shared\Application\BaseAction;

class HandoverEpcBimToDigitalTwinAction extends BaseAction
{
    /**
     * Handover EPC BIM model to operational Digital Twin with hash-chain integrity.
     *
     * @param  array<int, array{component_code: string, category: string, name: string, location_spec?: string, wbs_node_code?: string}>  $components
     * @return array{bim_model: BimModel, components_count: int, current_hash: string, version: int}
     */
    public function handle(
        string $projectCode,
        string $modelName,
        array $components
    ): array {
        return DB::transaction(function () use ($projectCode, $modelName, $components) {
            $latest = BimModel::where('project_code', $projectCode)
                ->orderByDesc('version')
                ->first();

            $nextVersion = $latest ? $latest->version + 1 : 1;
            $previousHash = $latest?->current_hash;

            $modelCode = 'BIM-'.strtoupper($projectCode).'-V'.$nextVersion;
            $payloadString = json_encode([
                'model_code' => $modelCode,
                'project_code' => $projectCode,
                'version' => $nextVersion,
                'components_count' => count($components),
                'previous_hash' => $previousHash,
            ]);

            $currentHash = hash('sha256', (string) $payloadString);

            $bimModel = BimModel::create([
                'model_code' => $modelCode,
                'project_code' => $projectCode,
                'version' => $nextVersion,
                'name' => $modelName,
                'previous_hash' => $previousHash,
                'current_hash' => $currentHash,
                'metadata' => ['handover_stage' => 'AS_BUILT_OPERATIONAL', 'components_count' => count($components)],
                'status' => 'APPROVED',
            ]);

            $createdCount = 0;
            foreach ($components as $comp) {
                TwinComponent::create([
                    'bim_model_id' => $bimModel->id,
                    'component_code' => $comp['component_code'],
                    'wbs_node_code' => $comp['wbs_node_code'] ?? 'WBS-HANDOVER-01',
                    'category' => $comp['category'],
                    'name' => $comp['name'],
                    'location_spec' => $comp['location_spec'] ?? 'Central Facility Zone',
                    'status' => 'OPERATIONAL',
                    'completion_pct' => 100,
                ]);
                $createdCount++;
            }

            $this->audit(
                action: 'proptech.bim.handover_completed',
                auditable: $bimModel,
                oldValues: ['previous_version' => $latest?->version],
                newValues: [
                    'model_code' => $modelCode,
                    'version' => $nextVersion,
                    'components_count' => $createdCount,
                    'current_hash' => $currentHash,
                ],
                correlationId: "prp_bim_handover_{$modelCode}",
                impactType: 'digital_twin_lifecycle'
            );

            $this->outbox(
                eventType: 'BimDigitalTwinHandoverCompleted',
                payload: [
                    'model_id' => $bimModel->id,
                    'model_code' => $modelCode,
                    'project_code' => $projectCode,
                    'version' => $nextVersion,
                    'components_count' => $createdCount,
                ],
                idempotencyKey: hash('sha256', "bim_handover_{$projectCode}_v{$nextVersion}")
            );

            return [
                'bim_model' => $bimModel,
                'components_count' => $createdCount,
                'current_hash' => $currentHash,
                'version' => $nextVersion,
            ];
        });
    }
}
