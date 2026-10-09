<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Outlet/pelanggan distributor (sell-out). */
class DistOutlet extends Model
{
    protected $table = 'dist_outlets';

    protected $fillable = [
        'distributor_id', 'code', 'name', 'segment', 'city', 'address', 'territory_id', 'is_active',
    ];

    protected $casts = ['territory_id' => 'integer', 'is_active' => 'boolean'];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'territory_id');
    }
}
