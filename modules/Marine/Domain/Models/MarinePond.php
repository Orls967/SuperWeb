<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarinePond extends Model
{
    protected $table = 'mar_ponds';

    protected $fillable = [
        'code',
        'name',
        'farmer_id',
        'capacity_units',
        'location',
    ];

    protected $casts = [
        'farmer_id' => 'integer',
        'capacity_units' => 'integer',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(MarineCycle::class, 'pond_id');
    }
}
