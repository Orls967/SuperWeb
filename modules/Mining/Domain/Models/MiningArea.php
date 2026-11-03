<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningArea extends Model
{
    protected $table = 'min_areas';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'pit_id',
        'area_code',
        'name',
        'status',
        'stop_reason',
        'stopped_at',
    ];

    protected $casts = [
        'stopped_at' => 'datetime',
    ];

    public function canOperate(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
