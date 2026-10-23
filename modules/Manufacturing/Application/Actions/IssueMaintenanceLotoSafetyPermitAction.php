<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Actions;

use DateTimeInterface;
use InvalidArgumentException;
use Modules\Manufacturing\Domain\Models\WorkCenter;
use Modules\Manufacturing\Domain\Models\WorkPermit;
use Modules\Shared\Application\BaseAction;

final class IssueMaintenanceLotoSafetyPermitAction extends BaseAction
{
    /**
     * Issues and approves a Lockout/Tagout (LOTO) or hazardous maintenance safety permit.
     */
    public function execute(
        WorkCenter $workCenter,
        string $permitType,
        string $hazards,
        string $controls,
        DateTimeInterface $validFrom,
        DateTimeInterface $validUntil,
        int $requestedByUserId
    ): WorkPermit {
        if (trim($hazards) === '' || trim($controls) === '') {
            throw new InvalidArgumentException('Deskripsi bahaya dan langkah mitigasi (controls) wajib disertakan untuk izin LOTO.');
        }

        if ($validUntil <= $validFrom) {
            throw new InvalidArgumentException('Batas waktu izin kerja harus lebih besar dari waktu mulai.');
        }

        return $this->transaction(function () use (
            $workCenter, $permitType, $hazards, $controls, $validFrom, $validUntil, $requestedByUserId
        ) {
            $number = 'LOTO-'.strtoupper(substr(md5((string) mt_rand()), 0, 8));

            $permit = WorkPermit::create([
                'number' => $number,
                'work_center_id' => $workCenter->id,
                'permit_type' => $permitType,
                'status' => 'active',
                'hazards' => $hazards,
                'controls' => $controls,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'requested_by_user_id' => $requestedByUserId,
                'approved_at' => now(),
            ]);

            $this->audit('mfg.loto_permit_issued', $permit, null, [
                'permit_number' => $permit->number,
                'work_center_id' => $workCenter->id,
                'permit_type' => $permitType,
                'valid_until' => $validUntil->format('Y-m-d H:i:s'),
            ]);

            return $permit;
        });
    }
}
