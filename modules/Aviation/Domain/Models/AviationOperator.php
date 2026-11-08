<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AviationOperator extends Model
{
    protected $table = 'avi_operators';

    protected $fillable = [
        'code',
        'name',
        'country',
        'status',
    ];

    public function aircraft(): HasMany
    {
        return $this->hasMany(AviationAircraft::class, 'operator_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(AviationSlot::class, 'operator_id');
    }

    public function flights(): HasMany
    {
        return $this->hasMany(AviationFlight::class, 'operator_id');
    }
}

