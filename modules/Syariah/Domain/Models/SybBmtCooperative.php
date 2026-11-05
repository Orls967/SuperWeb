<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SybBmtCooperative extends Model
{
    protected $table = 'syb_bmt_cooperatives';

    protected $fillable = [
        'cooperative_code',
        'name',
        'legal_entity_number',
        'city',
        'address',
        'dps_advisor',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function savings(): HasMany
    {
        return $this->hasMany(SybBmtSavings::class, 'cooperative_code', 'cooperative_code');
    }

    public function microFacilities(): HasMany
    {
        return $this->hasMany(SybMicroFacility::class, 'cooperative_code', 'cooperative_code');
    }

    public function groupFinancings(): HasMany
    {
        return $this->hasMany(SybGroupFinancing::class, 'cooperative_code', 'cooperative_code');
    }
}
