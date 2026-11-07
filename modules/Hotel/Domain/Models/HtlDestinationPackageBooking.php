<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class HtlDestinationPackageBooking extends Model
{
    use HasUuids;

    protected $table = 'htl_destination_package_bookings';

    protected $fillable = [
        'id',
        'destination_package_id',
        'guest_reference',
        'total_paid_idr',
        'escrow_held_idr',
        'escrow_status',
    ];

    protected $casts = [
        'total_paid_idr' => 'integer',
        'escrow_held_idr' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(DestinationPackage::class, 'destination_package_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(HtlDestinationPackageAllocation::class, 'package_booking_id');
    }

    public function releaseMilestone(int $amountIdr): void
    {
        if ($amountIdr > $this->escrow_held_idr) {
            throw new InvalidArgumentException(
                "Cannot release {$amountIdr} IDR because escrow remaining is {$this->escrow_held_idr} IDR."
            );
        }

        $this->escrow_held_idr -= $amountIdr;
        if ($this->escrow_held_idr === 0) {
            $this->escrow_status = 'FULLY_RELEASED';
        } else {
            $this->escrow_status = 'PARTIALLY_RELEASED';
        }

        $this->save();
    }
}
