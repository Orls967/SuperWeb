<?php

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Creator extends Model
{
    protected $table = 'ven_creators';

    protected $fillable = [
        'creator_code',
        'stage_name',
        'genre',
        'party_id',
        'status',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(ContentAsset::class, 'creator_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(RightsContract::class, 'creator_id');
    }
}
