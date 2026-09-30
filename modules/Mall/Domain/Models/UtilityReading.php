<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mall\Domain\Enums\UtilityType;

class UtilityReading extends Model
{
    protected $table = 'mall_utility_readings';

    protected $fillable = [
        'lease_id',
        'unit_id',
        'period_month',
        'utility_type',
        'previous_meter',
        'current_meter',
        'usage',
        'amount',
        'recorded_by',
        'recorded_at',
    ];

    protected $casts = [
        'utility_type' => UtilityType::class,
        'previous_meter' => 'float',
        'current_meter' => 'float',
        'usage' => 'float',
        'amount' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
