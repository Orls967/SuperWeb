<?php

declare(strict_types=1);

namespace Modules\Fashion\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FashionCollection extends Model
{
    protected $table = 'fsh_collections';

    protected $fillable = [
        'collection_code',
        'name',
        'season',
        'year',
        'status',
    ];

    public function samples(): HasMany
    {
        return $this->hasMany(FashionSample::class, 'collection_id');
    }

    public function buyPlans(): HasMany
    {
        return $this->hasMany(FashionBuyPlan::class, 'collection_id');
    }
}
