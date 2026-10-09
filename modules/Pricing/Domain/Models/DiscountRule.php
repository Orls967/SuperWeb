<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Diskon bertingkat: volume/bundle/combo/coupon (44.2). */
class DiscountRule extends Model
{
    use HasUuids;

    protected $table = 'pric_discount_rules';

    protected $fillable = [
        'code', 'name', 'kind', 'channel', 'region_code', 'threshold_qty',
        'threshold_amount_idr', 'percent_off', 'amount_off_idr', 'order',
        'applies_to', 'coupon_code', 'valid_from', 'valid_until', 'stackable', 'is_active',
    ];

    protected $casts = [
        'threshold_qty' => 'decimal:6', 'threshold_amount_idr' => 'integer',
        'percent_off' => 'decimal:4', 'amount_off_idr' => 'integer', 'order' => 'integer',
        'applies_to' => 'array', 'valid_from' => 'date', 'valid_until' => 'date',
        'stackable' => 'boolean', 'is_active' => 'boolean',
    ];

    public const KINDS = ['volume', 'bundle', 'combo', 'coupon'];

    public function isLive(?string $at = null): bool
    {
        $date = $at ?? now()->toDateString();

        return $this->is_active
            && $this->valid_from->toDateString() <= $date
            && ($this->valid_until === null || $this->valid_until->toDateString() >= $date);
    }
}
