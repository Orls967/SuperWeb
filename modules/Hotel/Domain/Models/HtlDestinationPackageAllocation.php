<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HtlDestinationPackageAllocation extends Model
{
    use HasUuids;

    protected $table = 'htl_destination_package_allocations';

    protected $fillable = [
        'id',
        'package_booking_id',
        'vendor_type',
        'vendor_reference',
        'ssp_amount_idr',
        'release_status',
        'released_at',
    ];

    protected $casts = [
        'ssp_amount_idr' => 'integer',
        'released_at' => 'immutable_datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HtlDestinationPackageBooking::class, 'package_booking_id');
    }

    public function markReleased(): void
    {
        $this->release_status = 'RELEASED';
        $this->released_at = CarbonImmutable::now();
        $this->save();
    }
}
