<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Enums\WorkPermitStatus;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Mining\Domain\StateMachines\WorkPermitStateMachine;

class ScanExpiringPermitsAction
{
    /**
     * Pindai dan perbarui izin kerja K3 tambang yang telah habis masa berlakunya (D94-5).
     *
     * @return array{
     *     total_active_scanned: int,
     *     expired_count: int,
     *     expired_permit_numbers: list<string>
     * }
     */
    public function execute(?CarbonImmutable $asOfTime = null): array
    {
        return DB::transaction(function () use ($asOfTime) {
            $now = $asOfTime ?? CarbonImmutable::now();

            $activePermits = MiningWorkPermit::whereIn('status', [WorkPermitStatus::ACTIVE->value, 'ACTIVE'])
                ->where('valid_until', '<', $now)
                ->get();

            $expiredPermits = [];

            foreach ($activePermits as $permit) {
                try {
                    $sm = new WorkPermitStateMachine($permit);
                    $sm->transitionTo(WorkPermitStatus::EXPIRED, 'Masa berlaku izin telah berakhir dalam pemindaian otomatis K3');
                } catch (\Throwable) {
                    $permit->status = WorkPermitStatus::EXPIRED->value;
                    $permit->save();
                }

                $expiredPermits[] = (string) $permit->permit_number;
            }

            return [
                'total_active_scanned' => $activePermits->count(),
                'expired_count' => count($expiredPermits),
                'expired_permit_numbers' => $expiredPermits,
            ];
        });
    }
}
