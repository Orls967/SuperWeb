<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarineCycle extends Model
{
    protected $table = 'mar_cycles';

    protected $fillable = [
        'cycle_number',
        'pond_id',
        'farmer_id',
        'species',
        'stocked_count',
        'initial_weight_grams_per_fish',
        'advance_amount',
        'status',
        'started_at',
    ];

    protected $casts = [
        'pond_id' => 'integer',
        'farmer_id' => 'integer',
        'stocked_count' => 'integer',
        'initial_weight_grams_per_fish' => 'integer',
        'advance_amount' => 'integer',
        'started_at' => 'datetime',
    ];

    public function pond(): BelongsTo
    {
        return $this->belongsTo(MarinePond::class, 'pond_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function feedLogs(): HasMany
    {
        return $this->hasMany(MarineFeedLog::class, 'cycle_id');
    }

    public function harvest(): HasOne
    {
        return $this->hasOne(MarineHarvest::class, 'cycle_id');
    }

    public function payout(): HasOne
    {
        return $this->hasOne(MarinePayout::class, 'cycle_id');
    }
}
