<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

use InvalidArgumentException;

/**
 * Sertifikat deterministik analisis degradasi baterai armada robotika.
 */
final readonly class BatteryDegradationCertificate
{
    /**
     * @param  list<array{robot_id:int, cycle_count:int, health_permil:int, replace_recommended:bool}>  $robots
     */
    public function __construct(
        public int $totalRobots,
        public int $replacementCount,
        public int $fleetAvgHealthPermil,
        public array $robots,
        public string $signatureHash,
    ) {}

    public function verify(): void
    {
        if ($this->totalRobots !== count($this->robots)) {
            throw new InvalidArgumentException('Sertifikat degradasi baterai rusak: totalRobots tidak cocok.');
        }

        $sumHealth = 0;
        $expectedReplace = 0;
        foreach ($this->robots as $row) {
            $sumHealth += (int) $row['health_permil'];
            if ((bool) $row['replace_recommended']) {
                $expectedReplace++;
            }
        }

        if ($expectedReplace !== $this->replacementCount) {
            throw new InvalidArgumentException('Sertifikat degradasi baterai rusak: replacementCount tidak cocok.');
        }

        $expectedAvg = $this->totalRobots > 0 ? intdiv($sumHealth, $this->totalRobots) : 0;
        if ($expectedAvg !== $this->fleetAvgHealthPermil) {
            throw new InvalidArgumentException('Sertifikat degradasi baterai rusak: fleetAvgHealthPermil tidak cocok.');
        }

        $expectedHash = self::computeHash(
            $this->totalRobots,
            $this->replacementCount,
            $this->fleetAvgHealthPermil,
            $this->robots,
        );

        if (! hash_equals($expectedHash, $this->signatureHash)) {
            throw new InvalidArgumentException('Sertifikat degradasi baterai rusak: signatureHash tidak valid.');
        }
    }

    /**
     * @param  list<array{robot_id:int, cycle_count:int, health_permil:int, replace_recommended:bool}>  $robots
     */
    public static function computeHash(
        int $totalRobots,
        int $replacementCount,
        int $fleetAvgHealthPermil,
        array $robots,
    ): string {
        $payload = json_encode([
            'total_robots' => $totalRobots,
            'replacement_count' => $replacementCount,
            'fleet_avg_health_permil' => $fleetAvgHealthPermil,
            'robots' => $robots,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }
}
