<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Catastrophe;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * Verifier katalog event (PR2): hash katalog sama, intensitas di dalam rentang peril, region dikenal,
 * dan damage tiap event ≤ eksposur region (vulnerability ≤ 100%).
 */
final class EventCatalogCertificateVerifier
{
    /**
     * @param  list<array{event_no: int, peril: string, region: string, intensity_bps: int, annual_rate_ppm: int}>  $events
     * @param  array<string, CatPerilParams>  $perilsByCode
     * @param  array<string, array<string, int>>  $exposureIdrByRegionPeril
     */
    public function verify(array $events, string $expectedHash, array $perilsByCode, array $exposureIdrByRegionPeril): true
    {
        $generator = new EventCatalogGenerator;
        if ($generator->catalogHash($events) !== $expectedHash) {
            throw new InvalidCertificateException('Hash katalog tidak cocok dengan sertifikat');
        }

        foreach ($events as $event) {
            $peril = $perilsByCode[$event['peril']] ?? throw new InvalidCertificateException("Peril {$event['peril']} tidak dikenal");
            if ($event['intensity_bps'] < $peril->minIntensityBps || $event['intensity_bps'] > $peril->maxIntensityBps) {
                throw new InvalidCertificateException("Intensitas event {$event['event_no']} di luar rentang peril");
            }
            if (! isset($exposureIdrByRegionPeril[$event['region']])) {
                throw new InvalidCertificateException("Region {$event['region']} tidak punya eksposur terdaftar");
            }

            $exposure = $exposureIdrByRegionPeril[$event['region']][$event['peril']] ?? 0;
            $damage = $generator->damageIdr($exposure, $generator->damageBps($peril->vulnerability, $event['intensity_bps']));
            if ($damage > $exposure) {
                throw new InvalidCertificateException("Damage event {$event['event_no']} melebihi eksposur region");
            }
        }

        return true;
    }
}
