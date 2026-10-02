<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Exceptions\DemurrageException;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\ContainerDwell;
use Modules\Logistics\Domain\Models\DdTariff;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;

class StartContainerDwellAction
{
    /**
     * Mulai hitungan demurrage (kontainer berada di pelabuhan/terminal) atau detention (kontainer di luar terminal).
     * Tarif dipilih berdasarkan kekhususan: lokasi+ukuran, lokasi, ukuran, umum.
     */
    public function execute(Container $container, Shipment $shipment, Location $location, string $kind, ?CarbonInterface $startedAt = null): ContainerDwell
    {
        $tariff = $this->resolveTariff($kind, $location, $container->size_type)
            ?? throw DemurrageException::noTariff($kind, $location->name);

        return DB::transaction(function () use ($container, $shipment, $location, $kind, $startedAt, $tariff) {
            $container = Container::whereKey($container->id)->lockForUpdate()->firstOrFail();

            if (ContainerDwell::where('container_id', $container->id)->where('kind', $kind)->where('status', ContainerDwell::STATUS_OPEN)->exists()) {
                throw DemurrageException::alreadyOpen($container->container_number, $kind);
            }

            return ContainerDwell::create([
                'container_id' => $container->id,
                'shipment_id' => $shipment->id,
                'shipper_id' => $shipment->shipper_id,
                'location_id' => $location->id,
                'tariff_id' => $tariff->id,
                'kind' => $kind,
                'status' => ContainerDwell::STATUS_OPEN,
                'started_at' => $startedAt ?? now(),
            ]);
        });
    }

    public function resolveTariff(string $kind, Location $location, string $sizeType): ?DdTariff
    {
        return DdTariff::where('kind', $kind)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('location_id', $location->id)->orWhereNull('location_id'))
            ->where(fn ($q) => $q->where('size_type', $sizeType)->orWhereNull('size_type'))
            ->get()
            ->sortByDesc(fn (DdTariff $t) => ($t->location_id ? 2 : 0) + ($t->size_type ? 1 : 0))
            ->first();
    }
}
