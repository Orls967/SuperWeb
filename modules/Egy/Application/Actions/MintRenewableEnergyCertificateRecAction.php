<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Egy\Domain\Models\RenewableEnergyCertificate;
use Modules\Shared\Application\BaseAction;

class MintRenewableEnergyCertificateRecAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): RenewableEnergyCertificate
    {
        return DB::transaction(function () use ($params) {
            $assetId = (string) $params['generation_asset_id'];
            $ownerEntityId = (string) ($params['owner_entity_id'] ?? 'PTY-GENCO-001');
            $mwh = (float) ($params['energy_mwh'] ?? $params['mwh_generated'] ?? 10.0);

            $certSerial = 'REC-'.strtoupper(substr(hash('sha256', "{$assetId}:{$ownerEntityId}:{$mwh}:".microtime()), 0, 12));

            $rec = RenewableEnergyCertificate::create([
                'id' => (string) Str::uuid(),
                'certificate_serial' => $certSerial,
                'generation_asset_id' => $assetId,
                'owner_entity_id' => $ownerEntityId,
                'energy_mwh' => $mwh,
                'is_retired' => false,
            ]);

            $this->audit(
                action: 'egy_rec_minted',
                auditable: $rec,
                newValues: ['certificate_serial' => $certSerial, 'mwh' => $mwh],
                impactType: 'carbon_esg',
            );

            return $rec;
        });
    }
}
