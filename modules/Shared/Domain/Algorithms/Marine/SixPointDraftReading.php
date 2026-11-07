<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Marine;

final readonly class SixPointDraftReading
{
    public function __construct(
        public float $draftFpM, // Forward Port (Haluan Kiri)
        public float $draftFsM, // Forward Starboard (Haluan Kanan)
        public float $draftMpM, // Midship Port (Tengah Kiri)
        public float $draftMsM, // Midship Starboard (Tengah Kanan)
        public float $draftApM, // Aft Port (Buritan Kiri)
        public float $draftAsM, // Aft Starboard (Buritan Kanan)
        public float $waterDensityTPerM3 = 1.025, // Densitas air (1.025 laut standar, 1.000 tawar, 1.010-1.020 payau)
        public float $ballastAndConsumablesTon = 0.0
    ) {}

    public function forwardMean(): float
    {
        return ($this->draftFpM + $this->draftFsM) / 2.0;
    }

    public function midshipMean(): float
    {
        return ($this->draftMpM + $this->draftMsM) / 2.0;
    }

    public function aftMean(): float
    {
        return ($this->draftApM + $this->draftAsM) / 2.0;
    }

    /**
     * Quarter-Mean Draft (dM): koreksi defleksi lambung kapal tongkang (hogging / sagging).
     * Formula: dM = (df + da + 6 * dm) / 8
     */
    public function quarterMeanDraft(): float
    {
        $df = $this->forwardMean();
        $dm = $this->midshipMean();
        $da = $this->aftMean();

        return ($df + $da + (6.0 * $dm)) / 8.0;
    }

    /**
     * Kemiringan memanjang (Trim) dalam meter: Trim = da - df.
     * Bernilai positif jika trim ke buritan (by the stern).
     */
    public function trimMeters(): float
    {
        return $this->aftMean() - $this->forwardMean();
    }

    /**
     * Kemiringan melintang (List / Heel) pada midship: List = dm_port - dm_starboard.
     */
    public function listMeters(): float
    {
        return $this->draftMpM - $this->draftMsM;
    }
}
