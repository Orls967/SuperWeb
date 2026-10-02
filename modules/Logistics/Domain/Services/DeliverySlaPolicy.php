<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Shipment;

/**
 * Aturan SLA pengiriman: batas waktu = booked_at + jam SLA per service level (config/logistics.php).
 */
class DeliverySlaPolicy
{
    /** Status resi yang masih berjalan dan terikat SLA. */
    public const ACTIVE_STATUSES = [
        ShipmentStatus::Booked,
        ShipmentStatus::PickedUp,
        ShipmentStatus::InTransit,
        ShipmentStatus::AtHub,
        ShipmentStatus::OutForDelivery,
        ShipmentStatus::OnHold,
        ShipmentStatus::CustomsHold,
        ShipmentStatus::Exception,
    ];

    public function hoursFor(ServiceLevel $level): int
    {
        return (int) config("logistics.sla_hours.{$level->value}", 72);
    }

    public function dueAt(Shipment $shipment): ?CarbonInterface
    {
        if (! $shipment->booked_at) {
            return null;
        }

        return $shipment->booked_at->copy()->addHours($this->hoursFor($shipment->service_level));
    }

    public function isActive(Shipment $shipment): bool
    {
        return in_array($shipment->status, self::ACTIVE_STATUSES, true);
    }

    public function isBreached(Shipment $shipment, ?CarbonInterface $now = null): bool
    {
        $due = $this->dueAt($shipment);

        return $due !== null && $this->isActive($shipment) && ($now ?? now())->greaterThan($due);
    }

    /**
     * Resi aktif yang sudah melewati batas SLA-nya.
     *
     * @return Builder<Shipment>
     */
    public function breachedQuery(?CarbonInterface $now = null): Builder
    {
        $now ??= now();

        return $this->windowQuery(fn (int $hours) => [null, $now->copy()->subHours($hours)]);
    }

    /**
     * Resi aktif yang belum terlambat tetapi jatuh tempo dalam $windowHours ke depan.
     *
     * @return Builder<Shipment>
     */
    public function atRiskQuery(?int $windowHours = null, ?CarbonInterface $now = null): Builder
    {
        $now ??= now();
        $window = $windowHours ?? (int) config('logistics.sla_at_risk_hours', 6);

        return $this->windowQuery(fn (int $hours) => [$now->copy()->subHours($hours), $now->copy()->subHours($hours)->addHours($window)]);
    }

    /**
     * @param  callable(int): array{0: ?CarbonInterface, 1: CarbonInterface}  $bounds  [lowerExclusive, upperInclusive] booked_at per jumlah jam SLA
     * @return Builder<Shipment>
     */
    protected function windowQuery(callable $bounds): Builder
    {
        $query = Shipment::query()->whereIn('status', array_map(fn ($s) => $s->value, self::ACTIVE_STATUSES))->whereNotNull('booked_at');

        return $query->where(function (Builder $outer) use ($bounds) {
            foreach (ServiceLevel::cases() as $level) {
                [$lower, $upper] = $bounds($this->hoursFor($level));
                $outer->orWhere(function (Builder $q) use ($level, $lower, $upper) {
                    $q->where('service_level', $level->value)->where('booked_at', '<=', $upper);
                    if ($lower !== null) {
                        $q->where('booked_at', '>', $lower);
                    }
                });
            }
        });
    }
}
