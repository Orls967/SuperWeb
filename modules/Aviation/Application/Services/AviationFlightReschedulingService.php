<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Models\AviationScheduleVersion;
use Modules\Aviation\Domain\StateMachines\ScheduleVersionStateMachine;

class AviationFlightReschedulingService
{
    public function __construct(
        protected ScheduleVersionStateMachine $stateMachine
    ) {}

    /**
     * Buat usulan draf versi jadwal penerbangan musiman (IATA Season).
     */
    public function createScheduleDraft(
        int $airlineId,
        string $seasonCode,
        int $versionNumber,
        string $effectiveFrom,
        string $effectiveUntil,
        ?string $notes = null
    ): AviationScheduleVersion {
        return AviationScheduleVersion::create([
            'airline_id' => $airlineId,
            'season_code' => $seasonCode,
            'version_number' => $versionNumber,
            'status' => ScheduleVersionStateMachine::STATUS_DRAFT,
            'effective_from' => $effectiveFrom,
            'effective_until' => $effectiveUntil,
            'notes' => $notes,
        ]);
    }

    /**
     * Ajukan draft jadwal untuk ditinjau oleh tim operasional & keselamatan.
     */
    public function submitScheduleForReview(int $versionId): AviationScheduleVersion
    {
        $version = AviationScheduleVersion::findOrFail($versionId);
        $version->status = $this->stateMachine->transition($version->status, ScheduleVersionStateMachine::STATUS_REVIEW);
        $version->save();

        return $version;
    }

    /**
     * Setujui dan umumkan versi jadwal ke publik (GDS publishing).
     */
    public function publishSchedule(int $versionId, ?int $approvedByUserId = null): AviationScheduleVersion
    {
        $version = AviationScheduleVersion::findOrFail($versionId);
        $version->status = $this->stateMachine->transition($version->status, ScheduleVersionStateMachine::STATUS_PUBLISHED);
        $version->published_at = now();
        $version->approved_by = $approvedByUserId;
        $version->save();

        return $version;
    }

    /**
     * Aktifkan musim operasional baru dan tandai versi sebelumnya sebagai superseded.
     */
    public function activateScheduleSeason(int $versionId): AviationScheduleVersion
    {
        return DB::transaction(function () use ($versionId) {
            $version = AviationScheduleVersion::lockForUpdate()->findOrFail($versionId);

            // Tandai versi aktif sebelumnya dari musim dan maskapai yang sama sebagai superseded
            AviationScheduleVersion::where('airline_id', $version->airline_id)
                ->where('season_code', $version->season_code)
                ->where('status', ScheduleVersionStateMachine::STATUS_ACTIVE)
                ->update(['status' => ScheduleVersionStateMachine::STATUS_SUPERSEDED]);

            $version->status = $this->stateMachine->transition($version->status, ScheduleVersionStateMachine::STATUS_ACTIVE);
            $version->save();

            return $version;
        });
    }
}
