<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Collection;
use Modules\Venue\Domain\Algorithms\CrowdSafety\CrowdDensitySurgePredictor;
use Modules\Venue\Domain\DTOs\CrowdZoneTelemetryDTO;
use Modules\Venue\Domain\Models\CrowdSafetyTelemetry;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\ValueObjects\CrowdHeatmapCell;

/**
 * Layanan Agregasi & Visualisasi Peta Panas Kerumunan Real-Time (Live Heatmap Streaming Service).
 * Mengubah data telemetri sensor sensor LiDAR dan scan turnstile gerbang menjadi
 * matriks visual koordinat SVG interaktif per zona venue.
 */
final class CrowdHeatmapStreamingService
{
    public function __construct(
        private readonly CrowdDensitySurgePredictor $surgePredictor
    ) {}

    /**
     * Dapatkan snapshot seluruh sel peta panas kerumunan untuk seluruh zona aktif.
     *
     * @return Collection<int, CrowdHeatmapCell>
     */
    public function getLiveHeatmapCells(int $venueId): Collection
    {
        $zones = EntertainmentZone::where('venue_id', $venueId)->get();
        $cells = collect();

        $zoneCoordinates = [
            'ZN-MAIN-STAGE' => '50,50 350,50 350,250 50,250',
            'ZN-EDM-ARENA' => '50,50 350,50 350,250 50,250',
            'ZN-VIP-DECK' => '370,50 550,50 550,150 370,150',
            'ZN-POOL-LOUNGE' => '370,170 550,170 550,320 370,320',
            'ZN-BEACH-FRONT' => '50,270 350,270 350,420 50,420',
            'ZN-FOOD-VILLAGE' => '370,340 550,340 550,450 370,450',
        ];

        foreach ($zones as $zone) {
            $headcount = (int) $zone->current_occupancy;
            $capLimit = max(1, (int) $zone->capacity_limit);
            $areaSqm = max(50, (int) intdiv($capLimit, 3)); // Perkiraan area 3 org/sqm max

            $densityBps = (int) intdiv($headcount * 10000, $areaSqm);
            $occupancyRatioBps = (int) intdiv($headcount * 10000, $capLimit);

            $isRestricted = (bool) $zone->crowd_lockdown_active;
            $color = CrowdHeatmapCell::determineStatusColor($occupancyRatioBps, $densityBps, $isRestricted);

            $polygon = $zoneCoordinates[$zone->zone_code] ?? '50,50 200,50 200,200 50,200';

            $cells->push(new CrowdHeatmapCell(
                zoneId: $zone->id,
                zoneCode: $zone->zone_code,
                zoneName: $zone->name,
                currentHeadcount: $headcount,
                capacityLimit: $capLimit,
                densitySqmBps: $densityBps,
                svgPolygonCoordinates: $polygon,
                colorHex: $color,
                isGateRestricted: $isRestricted
            ));
        }

        return $cells;
    }

    /**
     * Jalankan evaluasi prediktif dan simpan telemetri keselamatan terbaru untuk zona.
     */
    public function recordZoneTelemetrySnapshot(
        EntertainmentZone $zone,
        int $inflowRatePerMin,
        int $outflowRatePerMin,
        int $weatherFactorBps = 10000
    ): CrowdZoneTelemetryDTO {
        $headcount = (int) $zone->current_occupancy;
        $capLimit = max(1, (int) $zone->capacity_limit);
        $areaSqm = max(50, (int) intdiv($capLimit, 3));

        $prediction = $this->surgePredictor->predictSurge(
            zoneId: $zone->id,
            currentHeadcount: $headcount,
            zoneAreaSqm: $areaSqm,
            legalCapacityLimit: $capLimit,
            inflowVelocityPerMinute: $inflowRatePerMin,
            outflowVelocityPerMinute: $outflowRatePerMin,
            weatherFactorBps: $weatherFactorBps
        );

        $occupancyRatioBps = (int) intdiv($headcount * 10000, $capLimit);
        $heatColor = CrowdHeatmapCell::determineStatusColor($occupancyRatioBps, $prediction->predictedDensityBps, $prediction->gateRestricted);

        $telemetryCode = sprintf('CST-%d-%d-%d', $zone->id, $headcount, time());

        CrowdSafetyTelemetry::create([
            'telemetry_code' => $telemetryCode,
            'zone_id' => $zone->id,
            'current_headcount' => $headcount,
            'density_capacity_limit' => $capLimit,
            'occupancy_percentage' => round(($headcount / $capLimit) * 100, 2),
            'predicted_headcount_15m' => $prediction->predictedHeadcount15m,
            'recommended_action' => $prediction->recommendedAction,
            'gate_restricted' => $prediction->gateRestricted,
        ]);

        if ($prediction->gateRestricted && ! $zone->crowd_lockdown_active) {
            $zone->crowd_lockdown_active = true;
            $zone->save();
        }

        return new CrowdZoneTelemetryDTO(
            zoneId: $zone->id,
            zoneCode: $zone->zone_code,
            zoneName: $zone->name,
            currentHeadcount: $headcount,
            predictedHeadcount15m: $prediction->predictedHeadcount15m,
            capacityLimit: $capLimit,
            occupancyRateBps: $occupancyRatioBps,
            densitySqmBps: $prediction->predictedDensityBps,
            recommendedAction: $prediction->recommendedAction,
            gateRestricted: $prediction->gateRestricted,
            svgHeatColor: $heatColor
        );
    }

    /**
     * Hasilkan markup SVG lengkap untuk disisipkan langsung pada tampilan dashboard Blade.
     */
    public function renderSvgHeatmapMarkup(int $venueId, int $viewWidth = 600, int $viewHeight = 500): string
    {
        $cells = $this->getLiveHeatmapCells($venueId);

        $svg = sprintf(
            '<svg viewBox="0 0 %d %d" class="w-full h-auto rounded-xl bg-slate-900 border border-slate-700 shadow-2xl" xmlns="http://www.w3.org/2000/svg">',
            $viewWidth,
            $viewHeight
        );

        $svg .= '<defs><filter id="heatGlow" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="8" result="blur" /><feComposite in="SourceGraphic" in2="blur" operator="over" /></filter></defs>';

        foreach ($cells as $cell) {
            $svg .= sprintf(
                '<polygon points="%s" fill="%s" fill-opacity="0.65" stroke="%s" stroke-width="3" filter="url(#heatGlow)" class="transition-all duration-300 hover:fill-opacity-90 cursor-pointer" data-zone="%s" data-headcount="%d" data-cap="%d" />',
                $cell->svgPolygonCoordinates,
                $cell->colorHex,
                $cell->colorHex,
                htmlspecialchars($cell->zoneName),
                $cell->currentHeadcount,
                $cell->capacityLimit
            );

            // Label zona di tengah poligon
            $coords = explode(' ', $cell->svgPolygonCoordinates);
            $first = explode(',', $coords[0] ?? '50,50');
            $lblX = (int) ($first[0] ?? 50) + 15;
            $lblY = (int) ($first[1] ?? 50) + 30;

            $svg .= sprintf(
                '<text x="%d" y="%d" fill="#FFFFFF" font-size="12" font-weight="bold" font-family="sans-serif">%s (%d/%d)</text>',
                $lblX,
                $lblY,
                htmlspecialchars($cell->zoneCode),
                $cell->currentHeadcount,
                $cell->capacityLimit
            );
        }

        $svg .= '</svg>';

        return $svg;
    }
}
