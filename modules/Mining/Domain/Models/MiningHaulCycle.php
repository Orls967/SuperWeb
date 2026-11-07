<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Mining\Domain\Enums\HaulCycleStatus;

class MiningHaulCycle extends Model
{
    use HasUuids;

    protected $table = 'min_haul_cycles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'pit_id',
        'truck_id',
        'shovel_id',
        'shift_date',
        'shift_code',
        'cycle_seq',
        'status',
        'target_payload_kg',
        'actual_payload_kg',
        'distance_meters',
        'fuel_consumed_ml',
        'ton_km_scaled',
        'is_override',
        'override_reason',
        'override_by_user_id',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'cycle_seq' => 'integer',
        'target_payload_kg' => 'integer',
        'actual_payload_kg' => 'integer',
        'distance_meters' => 'integer',
        'fuel_consumed_ml' => 'integer',
        'ton_km_scaled' => 'integer',
        'is_override' => 'boolean',
        'status' => HaulCycleStatus::class,
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function pit(): BelongsTo
    {
        return $this->belongsTo(MiningPit::class, 'pit_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(MiningEquipment::class, 'truck_id');
    }

    public function shovel(): BelongsTo
    {
        return $this->belongsTo(MiningEquipment::class, 'shovel_id');
    }

    public function overrideByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by_user_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(MiningHaulCycleHistory::class, 'haul_cycle_id');
    }
}

