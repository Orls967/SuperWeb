<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\GateScanLog;
use Modules\Venue\Domain\Models\TurnstileDevice;

class ReconcileTurnstileLogAction extends BaseAction
{
    /**
     * Membandingkan jumlah ketukan sensor sinar optik turnstile fisik dengan jumlah log pindaian DB.
     *
     * @return array{
     *     device_code: string,
     *     hardware_beam_trips: int,
     *     database_allowed_scans: int,
     *     unauthorized_tailgating_trips: int,
     *     reconciliation_status: string
     * }
     */
    public function execute(TurnstileDevice $device): array
    {
        $allowedScans = GateScanLog::where('gate_code', $device->device_code)
            ->where('scan_result', 'ALLOWED')
            ->count();

        $trips = (int) $device->optical_beam_trips;
        $tailgating = max(0, $trips - $allowedScans);

        $status = match (true) {
            $tailgating > 10 => 'TAILGATING_BREACH_SUSPECTED',
            $tailgating > 0 => 'MINOR_DISCREPANCY',
            default => 'SYNCHRONIZED',
        };

        return [
            'device_code' => $device->device_code,
            'hardware_beam_trips' => $trips,
            'database_allowed_scans' => $allowedScans,
            'unauthorized_tailgating_trips' => $tailgating,
            'reconciliation_status' => $status,
        ];
    }
}
