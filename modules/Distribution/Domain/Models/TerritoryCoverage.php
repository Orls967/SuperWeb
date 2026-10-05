<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pemetaan distributor ↔ wilayah (eksklusif / non-eksklusif). */
class TerritoryCoverage extends Model
{
    protected $table = 'dist_territory_coverage';

    protected $fillable = ['distributor_id', 'territory_id', 'exclusive', 'valid_from', 'valid_until'];

    protected $casts = ['exclusive' => 'boolean', 'valid_from' => 'date', 'valid_until' => 'date'];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'territory_id');
    }
}
