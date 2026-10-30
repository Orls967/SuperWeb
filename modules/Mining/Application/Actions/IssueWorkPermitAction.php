<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Shared\Application\BaseAction;

class IssueWorkPermitAction extends BaseAction
{
    /**
     * @return object{permit: MiningWorkPermit}
     */
    public function handle(
        string $siteId,
        string $areaId,
        string $workerPartyId,
        string $permitType,
        string $validFrom,
        string $validUntil,
        int $approverUserId,
        string $idempotencyKey = ''
    ): object {
        if (empty(trim($idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency key wajib diisi.');
        }

        return DB::transaction(function () use (
            $siteId,
            $areaId,
            $workerPartyId,
            $permitType,
            $validFrom,
            $validUntil
        ) {
            $permitNumber = sprintf('PTW-%s-%s', date('ymd'), strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)));

            $permit = MiningWorkPermit::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'area_id' => $areaId,
                'permit_number' => $permitNumber,
                'permit_type' => $permitType,
                'supervisor_name' => 'HSE Superintendent',
                'worker_party_id' => $workerPartyId,
                'valid_from' => Carbon::parse($validFrom),
                'valid_until' => Carbon::parse($validUntil),
                'approval_status' => 'APPROVED',
                'status' => 'ACTIVE',
            ]);

            return (object) ['permit' => $permit];
        });
    }
}
