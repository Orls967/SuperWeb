<?php

declare(strict_types=1);

namespace Modules\Esg\Application\Listeners;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Egy\Events\EgyMeterReadV1;
use Modules\Esg\Domain\Models\EsgEmission;

class RecordScopeTwoElectricityEmissionsListener
{
    /** @var array<string, int> Grams of CO2 per kWh */
    private const GRID_EMISSION_FACTORS = [
        'JAMALI' => 800,
        'SUMATERA' => 750,
        'KALIMANTAN' => 850,
        'SULAWESI' => 700,
        'PAPUA' => 900,
    ];

    public function getGridEmissionFactor(string $region): int
    {
        if (! isset(self::GRID_EMISSION_FACTORS[$region])) {
            throw new InvalidArgumentException("Unknown grid emission region: {$region}");
        }

        return self::GRID_EMISSION_FACTORS[$region];
    }

    public function handle(EgyMeterReadV1 $event): EsgEmission
    {
        $factorGrams = $this->getGridEmissionFactor($event->region);

        // Check idempotency by source_reference
        $existing = EsgEmission::where('source_reference', $event->readingId)->first();
        if ($existing) {
            return $existing;
        }

        $kwh = $event->intervalWh / 1000.0;
        $kgCo2 = ($kwh * $factorGrams) / 1000.0;

        return EsgEmission::create([
            'emission_number' => 'EM-SCOPE2-'.$event->readingId,
            'entity_id' => $event->propertyId,
            'scope' => 'SCOPE_2',
            'activity_type' => 'electricity_grid',
            'activity_data_amount' => $kwh,
            'activity_uom' => 'kWh',
            'emission_factor' => $factorGrams / 1000.0,
            'co2e_kg' => $kgCo2,
            'reporting_period' => CarbonImmutable::parse($event->readingAt)->format('Y-m-d'),
            'source_module' => 'egy',
            'source_reference' => $event->readingId,
            'metadata' => $event->toPayload(),
        ]);
    }
}
