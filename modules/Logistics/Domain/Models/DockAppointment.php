<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Exceptions\ScheduleConflictException;

/**
 * Loading dock appointment at Duta Mall.
 *
 * Each appointment locks a dock_code+date+time slot.
 * Satpam checks in/out vehicles at the dock.
 */
class DockAppointment extends LogisticsEntity
{
    protected $table = 'lgx_dock_appointments';

    protected $fillable = [
        'property_id',
        'tenant_id',
        'shipment_id',
        'dock_code',
        'date',
        'start_time',
        'end_time',
        'status',
        'vehicle_plate',
        'driver_name',
        'notes',
        'checked_in_at',
        'checked_out_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    /**
     * Check whether this time slot overlaps with another appointment.
     */
    public static function hasConflict(int $propertyId, string $dockCode, string $date, string $startTime, string $endTime, ?int $excludeId = null): bool
    {
        $query = static::where('property_id', $propertyId)
            ->where('dock_code', $dockCode)
            ->whereDate('date', $date)
            ->whereIn('status', ['reserved', 'checked_in'])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($inner) use ($startTime, $endTime) {
                    $inner->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Reserve a dock slot with overlap validation.
     */
    public static function reserve(array $data): self
    {
        return DB::transaction(function () use ($data) {
            if (static::hasConflict(
                $data['property_id'],
                $data['dock_code'],
                $data['date'],
                $data['start_time'],
                $data['end_time'],
            )) {
                throw new ScheduleConflictException("Dock {$data['dock_code']} sudah terpakai pada waktu tersebut.");
            }

            return static::create(array_merge($data, ['status' => 'reserved']));
        });
    }

    public function checkIn(): void
    {
        $this->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
    }

    public function checkOut(): void
    {
        if ($this->status !== 'checked_in') {
            throw new \DomainException('Kendaraan belum check-in; check-out ditolak.');
        }

        $this->update([
            'status' => 'completed',
            'checked_out_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }
}
