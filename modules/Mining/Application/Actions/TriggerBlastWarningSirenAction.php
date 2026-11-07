<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Enums\BlastClearanceStatus;
use Modules\Mining\Domain\Models\MiningBlastClearanceRun;
use Modules\Mining\Domain\StateMachines\BlastClearanceRunStateMachine;

/**
 * Domain Action: Aktivasi Protokol Sirine Peringatan Peledakan Tambang.
 * Mengendalikan peniupan sirine multi-tahap sesuai standar KTT (Kepala Teknik Tambang)
 * dan memvalidasi izin master blaster sebelum sirine diaktifkan.
 */
class TriggerBlastWarningSirenAction
{
    public function __construct(
        private readonly BlastClearanceRunStateMachine $stateMachine
    ) {}

    /**
     * @param  array{
     *     blast_clearance_run_id: string,
     *     siren_stage: string,
     *     blaster_in_charge_kjl: string,
     *     decibel_confirmed?: float
     * } $data
     * @return array{
     *     run_code: string,
     *     siren_stage: string,
     *     status: string,
     *     siren_duration_seconds: int,
     *     activated_at: string
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $run = MiningBlastClearanceRun::query()->findOrFail($data['blast_clearance_run_id']);

        if (! $run->all_personnel_evacuated) {
            throw new InvalidArgumentException('Sirine tidak boleh dibunyikan bila evakuasi personel belum tuntas 100%.');
        }

        $currentStatus = BlastClearanceStatus::tryFrom($run->status) ?? BlastClearanceStatus::DRAFT;
        $targetStatus = BlastClearanceStatus::SIREN_ACTIVE;

        if ($this->stateMachine->canTransition($currentStatus, $targetStatus)) {
            $run->status = $targetStatus->value;
        }

        $run->siren_activated_at = now();

        $stage = strtoupper(trim($data['siren_stage']));
        $durationSeconds = match ($stage) {
            'STAGE_1_15MIN' => 900,
            'STAGE_2_5MIN' => 300,
            'STAGE_3_FINAL_1MIN' => 60,
            default => 60,
        };

        $meta = $run->metadata ?? [];
        $meta['siren_log'] = [
            'stage' => $stage,
            'duration_seconds' => $durationSeconds,
            'blaster_kjl' => $data['blaster_in_charge_kjl'],
            'decibel_confirmed' => (float) ($data['decibel_confirmed'] ?? 115.0),
            'activated_at' => now()->toIso8601String(),
        ];
        $run->metadata = $meta;
        $run->save();

        return [
            'run_code' => $run->run_code,
            'siren_stage' => $stage,
            'status' => $run->status,
            'siren_duration_seconds' => $durationSeconds,
            'activated_at' => now()->toIso8601String(),
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

        if (empty($data['blaster_in_charge_kjl'])) {
            throw new InvalidArgumentException('Nomor Kartu Izin Meledakkan (KIM / KJL) blaster penanggung jawab wajib diisi.');
        }
    }
}
