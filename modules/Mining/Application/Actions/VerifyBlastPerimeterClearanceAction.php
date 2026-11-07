<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Contracts\PersonnelTelematicsGeofenceGateway;
use Modules\Mining\Domain\Enums\BlastClearanceStatus;
use Modules\Mining\Domain\Models\MiningBlastClearanceRun;
use Modules\Mining\Domain\StateMachines\BlastClearanceRunStateMachine;

/**
 * Domain Action: Verifikasi Sterilisasi Perimeter Peledakan (Blast Clearance Verification).
 * Menginterogasi GPS telematics gateway untuk memastikan tidak ada manusia atau armada
 * di dalam zona bahaya peledakan sebelum proses sirine dan inisiasi peledakan.
 */
class VerifyBlastPerimeterClearanceAction
{
    public function __construct(
        private readonly PersonnelTelematicsGeofenceGateway $telematicsGateway,
        private readonly BlastClearanceRunStateMachine $stateMachine
    ) {}

    /**
     * @param  array{
     *     blast_clearance_run_id: string,
     *     override_allow_hot_switch?: bool,
     *     verifier_name?: string
     * } $data
     * @return array{
     *     run_code: string,
     *     status: string,
     *     is_perimeter_safe: bool,
     *     detected_breaches_count: int,
     *     breached_personnel: list<array{worker_id: string, name: string, lat: float, lng: float, distance_meters: float}>,
     *     rejection_reason?: string|null
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $run = MiningBlastClearanceRun::query()->findOrFail($data['blast_clearance_run_id']);

        $centerLat = (float) $run->center_latitude;
        $centerLng = (float) $run->center_longitude;
        $radiusM = (float) $run->safety_radius_meters;

        $detectedPersonnel = $this->telematicsGateway->scanPersonnelInRadius($centerLat, $centerLng, $radiusM);
        $breachCount = count($detectedPersonnel);
        $isSafe = $breachCount === 0;

        $currentStatus = BlastClearanceStatus::tryFrom($run->status) ?? BlastClearanceStatus::DRAFT;

        if ($isSafe) {
            // Transisi ke ALL_CLEAR
            if ($this->stateMachine->canTransition($currentStatus, BlastClearanceStatus::ALL_CLEAR)) {
                $run->status = BlastClearanceStatus::ALL_CLEAR->value;
            }
            $run->all_personnel_evacuated = true;
            $run->clearance_notes = 'Perimeter verifikasi sukses: 0 personel terdeteksi di radius '.$radiusM.'m.';
        } else {
            // Ada pelanggaran sterilisasi: tolak dan transisikan ke BLOCKED
            if ($this->stateMachine->canTransition($currentStatus, BlastClearanceStatus::BLOCKED)) {
                $run->status = BlastClearanceStatus::BLOCKED->value;
            }
            $run->all_personnel_evacuated = false;
            $run->clearance_notes = "TERDETEKSI PELANGGARAN: {$breachCount} personel aktif berada di dalam perimeter bahaya.";
        }

        $meta = $run->metadata ?? [];
        $meta['last_perimeter_scan'] = [
            'scanned_at' => now()->toIso8601String(),
            'breach_count' => $breachCount,
            'breached_personnel' => $detectedPersonnel,
            'verifier' => $data['verifier_name'] ?? 'Blast Safety Officer',
        ];
        $run->metadata = $meta;
        $run->save();

        return [
            'run_code' => $run->run_code,
            'status' => $run->status,
            'is_perimeter_safe' => $isSafe,
            'detected_breaches_count' => $breachCount,
            'breached_personnel' => $detectedPersonnel,
            'rejection_reason' => $isSafe ? null : "Dilarang tembak: {$breachCount} personel masih berada di radius bahaya peledakan.",
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['blast_clearance_run_id'])) {
            throw new InvalidArgumentException('Blast clearance run ID harus diisi.');
        }
    }
}
