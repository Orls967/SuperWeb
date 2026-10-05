<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Wave picking: kelompok tugas pick yang dirilis bersama. */
class Wave extends Model
{
    protected $table = 'wms_waves';

    protected $fillable = ['code', 'status', 'strategy', 'notes', 'released_at'];

    protected $casts = ['released_at' => 'datetime'];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'wave_id');
    }
}
