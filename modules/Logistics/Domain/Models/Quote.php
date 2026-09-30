<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\TransportMode;

class Quote extends LogisticsEntity
{
    protected $table = 'lgx_quotes';

    protected $fillable = [
        'uuid',
        'shipper_id',
        'origin_location_id',
        'destination_location_id',
        'service_level',
        'mode',
        'packages_payload',
        'actual_weight_kg',
        'chargeable_weight_kg',
        'base_freight_idr',
        'surcharges_breakdown',
        'total_surcharges_idr',
        'subtotal_idr',
        'vat_rate',
        'vat_amount_idr',
        'total_amount_idr',
        'payload_hash',
        'expires_at',
        'is_booked',
    ];

    protected $casts = [
        'shipper_id' => 'integer',
        'origin_location_id' => 'integer',
        'destination_location_id' => 'integer',
        'service_level' => ServiceLevel::class,
        'mode' => TransportMode::class,
        'packages_payload' => 'array',
        'actual_weight_kg' => 'decimal:4',
        'chargeable_weight_kg' => 'decimal:4',
        'base_freight_idr' => 'integer',
        'surcharges_breakdown' => 'array',
        'total_surcharges_idr' => 'integer',
        'subtotal_idr' => 'integer',
        'vat_rate' => 'decimal:4',
        'vat_amount_idr' => 'integer',
        'total_amount_idr' => 'integer',
        'expires_at' => 'datetime',
        'is_booked' => 'boolean',
    ];

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function secondsRemaining(): int
    {
        return (int) max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * Compute canonical SHA-256 payload hash of the quote line items.
     */
    public function computePayloadHash(): string
    {
        $serviceLevel = $this->service_level instanceof ServiceLevel
            ? $this->service_level->value
            : (string) $this->service_level;

        $mode = $this->mode instanceof TransportMode
            ? $this->mode->value
            : (string) $this->mode;

        $expiresTimestamp = $this->expires_at instanceof CarbonInterface
            ? $this->expires_at->getTimestamp()
            : Carbon::parse($this->expires_at)->getTimestamp();

        $canonicalString = implode('|', [
            $this->uuid,
            (int) $this->shipper_id,
            (int) $this->origin_location_id,
            (int) $this->destination_location_id,
            $serviceLevel,
            $mode,
            (string) $this->chargeable_weight_kg,
            (int) $this->base_freight_idr,
            (int) $this->total_surcharges_idr,
            (int) $this->subtotal_idr,
            (int) $this->vat_amount_idr,
            (int) $this->total_amount_idr,
            $expiresTimestamp,
        ]);

        return hash('sha256', $canonicalString);
    }

    /**
     * Verify that the stored payload hash matches the current quote contents.
     */
    public function verifyHash(): bool
    {
        return hash_equals((string) $this->payload_hash, $this->computePayloadHash());
    }

    /**
     * Check if the quote is valid for booking.
     */
    public function canBeBooked(): bool
    {
        return ! $this->is_booked && ! $this->isExpired() && $this->verifyHash();
    }
}
