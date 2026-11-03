<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Services;

use Modules\Logistics\Contracts\VesselRegistry;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\Vessel;

final class LogisticsVesselRegistry implements VesselRegistry
{
    public function findByImo(string $imo): ?array
    {
        $vessel = Vessel::where('imo_number', $imo)->first();
        if (! $vessel) {
            return null;
        }

        return [
            'id' => (int) $vessel->id,
            'imo' => (string) $vessel->imo_number,
            'name' => (string) $vessel->name,
            'loa_cm' => (int) ($vessel->loa_cm ?? 20_000), // Panjang LOA default 200 meter bila kolom belum ada
        ];
    }

    public function containerById(int $containerId): ?array
    {
        $container = Container::find($containerId);
        if (! $container) {
            return null;
        }

        return [
            'id' => (int) $container->id,
            'number' => (string) $container->container_number,
            'iso_type' => (string) ($container->size_type ?? '40HC'),
            'line_party_id' => 1,
        ];
    }
}
