<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PharmacyBranch extends Model
{
    protected $table = 'hsp_pharmacy_branches';

    protected $fillable = [
        'branch_code',
        'name',
        'type',
        'city',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(EPharmacyOrder::class, 'pharmacy_branch_id');
    }
}
