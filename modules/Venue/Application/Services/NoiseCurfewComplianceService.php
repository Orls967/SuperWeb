<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\VenueNoiseCurfewReading;

final class NoiseCurfewComplianceService
{
    public const DAYTIME_LIMIT_DBA = 85;
    public const NIGHT_CURFEW_LIMIT_DBA = 70;
    public const POST_MIDNIGHT_LIMIT_DBA = 60;

    /**
     * Ingest decibel reading and verify municipal sound pollution compliance.
     *
     * @return array{
     *     venue_code: string,
     *     reading_dba: int,
     *     threshold_dba: int,
     *     is_curfew_breach: bool,
     *     penalty_idr: int,
     *     advisory_notice: string
     * }
     */
    public function recordSoundLevel(
        EntertainmentVenue $venue,
        int $readingDba,
        string $sensorCode = 'MIC-PERIMETER-01'
    ): array {
        $now = CarbonImmutable::now();
        $hour = (int) $now->format('H');

        $threshold = self::DAYTIME_LIMIT_DBA;
        if ($hour >= 23 || $hour < 1) {
            $threshold = self::NIGHT_CURFEW_LIMIT_DBA;
        } elseif ($hour >= 1 && $hour < 6) {
            $threshold = self::POST_MIDNIGHT_LIMIT_DBA;
        }

        $isBreach = $readingDba > $threshold;
        $penalty = 0;
        $advisory = 'Tingkat kebisingan akustik memenuhi standar izin lingkungan.';

        if ($isBreach) {
            $excessDba = $readingDba - $threshold;
            // Denda per dB pelanggaran: Rp 2.500.000 per dB
            $penalty = (int) ($excessDba * 2500000);
            $advisory = "PERINGATAN KEBISINGAN: Tingkat desibel ({$readingDba} dBA) melampaui batas kurva ({$threshold} dBA) sebesar {$excessDba} dB. Turunkan master output audio segera.";
        }

        VenueNoiseCurfewReading::create([
            'venue_id' => $venue->id,
            'decibel_level_db' => $readingDba,
            'sensor_code' => $sensorCode,
            'frequency_weighting' => 'dBA',
            'recorded_at' => $now,
            'is_curfew_breach' => $isBreach,
            'penalty_assessment_idr' => $penalty,
        ]);

        return [
            'venue_code' => $venue->venue_code,
            'reading_dba' => $readingDba,
            'threshold_dba' => $threshold,
            'is_curfew_breach' => $isBreach,
            'penalty_idr' => $penalty,
            'advisory_notice' => $advisory,
        ];
    }
}
