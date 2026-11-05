<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Catastrophe;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Chain\CanonicalHashChain;
use Modules\Shared\Domain\Algorithms\Random\MinStd;

/**
 * Generator katalog event katastrofe sintetis yang deterministik (seed + parameter → katalog identik)
 * dan simulator Year Loss Table. Seluruh keputusan acak memakai MinStd (G7-D2).
 *
 * Kompleksitas: katalog O(E); YLT O(Y·E) draw untuk Y tahun dan E event (batas praktis Y·E ≤ 10⁷).
 * Damage = eksposur × vulnerability(intensitas) dengan interpolasi linear rasional, HalfUp ke rupiah.
 */
final class EventCatalogGenerator
{
    /**
     * @param  list<CatPerilParams>  $perils
     * @param  list<string>  $regionCodes
     * @return list<array{event_no: int, peril: string, region: string, intensity_bps: int, annual_rate_ppm: int}>
     */
    public function generate(int $seed, array $perils, array $regionCodes, int $eventsPerPeril): array
    {
        if ($perils === [] || $regionCodes === [] || $eventsPerPeril <= 0) {
            throw new InvalidArgumentException('Peril, region, dan jumlah event per peril wajib diisi.');
        }

        $rng = new MinStd($seed);
        $events = [];
        $eventNo = 0;

        foreach ($perils as $peril) {
            $rate = BigRational::of($peril->annualFrequencyPpm)->dividedBy($eventsPerPeril)->toScale(0, RoundingMode::HalfUp)->toInt();
            $range = $peril->maxIntensityBps - $peril->minIntensityBps;

            for ($i = 0; $i < $eventsPerPeril; $i++) {
                $region = $regionCodes[$rng->nextBelow(count($regionCodes))];
                // Produk dua seragam → skew ke intensitas rendah (event kecil lebih sering).
                $draw = $rng->nextBelow(10000) * $rng->nextBelow(10000);
                $events[] = [
                    'event_no' => ++$eventNo,
                    'peril' => $peril->code,
                    'region' => $region,
                    'intensity_bps' => $peril->minIntensityBps + intdiv($range * $draw, 100_000_000),
                    'annual_rate_ppm' => max($rate, 1),
                ];
            }
        }

        return $events;
    }

    /** Hash rantai katalog (sertifikat kesamaan lintas run). */
    public function catalogHash(array $events): string
    {
        $chain = new CanonicalHashChain;
        $hash = CanonicalHashChain::GENESIS_HASH;
        foreach ($events as $event) {
            $hash = $chain->link($hash, $event);
        }

        return $hash;
    }

    /**
     * @param  list<array{intensity_bps: int, damage_bps: int}>  $curve
     */
    public function damageBps(array $curve, int $intensityBps): int
    {
        $first = $curve[0];
        if ($intensityBps <= $first['intensity_bps']) {
            return $first['damage_bps'];
        }

        for ($i = 1, $count = count($curve); $i < $count; $i++) {
            $left = $curve[$i - 1];
            $right = $curve[$i];
            if ($intensityBps <= $right['intensity_bps']) {
                $span = $right['intensity_bps'] - $left['intensity_bps'];

                return $left['damage_bps'] + BigRational::of(($right['damage_bps'] - $left['damage_bps']) * ($intensityBps - $left['intensity_bps']))
                    ->dividedBy($span)->toScale(0, RoundingMode::HalfUp)->toInt();
            }
        }

        return $curve[count($curve) - 1]['damage_bps'];
    }

    public function damageIdr(int $exposureIdr, int $damageBps): int
    {
        return BigRational::of($exposureIdr * $damageBps)->dividedBy(10000)->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    /**
     * Simulasi YLT: tiap tahun, tiap event terjadi bila draw < annual_rate_ppm (Bernoulli per event).
     *
     * @param  list<array{event_no: int, peril: string, region: string, intensity_bps: int, annual_rate_ppm: int}>  $catalog
     * @param  array<string, array<string, int>>  $exposureIdrByRegionPeril  region => peril => eksposur
     * @param  array<string, CatPerilParams>  $perilsByCode
     * @return list<list<array{event_no: int, loss_idr: int}>>
     */
    public function simulateYears(int $seed, array $catalog, array $exposureIdrByRegionPeril, array $perilsByCode, int $years): array
    {
        if ($years <= 0) {
            throw new InvalidArgumentException('Jumlah tahun simulasi harus positif.');
        }

        $damageBpsByEvent = [];
        foreach ($catalog as $event) {
            $curve = ($perilsByCode[$event['peril']] ?? throw new InvalidArgumentException("Peril {$event['peril']} tidak dikenal."))->vulnerability;
            $damageBpsByEvent[$event['event_no']] = $this->damageBps($curve, $event['intensity_bps']);
        }

        $rng = new MinStd($seed);
        $table = [];
        for ($year = 0; $year < $years; $year++) {
            $occurred = [];
            foreach ($catalog as $event) {
                if ($rng->nextBelow(1_000_000) < $event['annual_rate_ppm']) {
                    $exposure = $exposureIdrByRegionPeril[$event['region']][$event['peril']] ?? 0;
                    $loss = $this->damageIdr($exposure, $damageBpsByEvent[$event['event_no']]);
                    if ($loss > 0) {
                        $occurred[] = ['event_no' => $event['event_no'], 'loss_idr' => $loss];
                    }
                }
            }
            $table[] = $occurred;
        }

        return $table;
    }
}
