<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YardBlock extends Model
{
    protected $table = 'prt_yard_blocks';

    protected $fillable = [
        'code',
        'name',
        'capacity_teu',
        'block_type',
        'metadata',
    ];

    protected $casts = [
        'capacity_teu' => 'integer',
        'metadata' => 'array',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(YardSlot::class, 'yard_block_id');
    }
}
