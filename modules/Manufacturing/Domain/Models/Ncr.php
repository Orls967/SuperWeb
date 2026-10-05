<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** NCR (ketidaksesuaian) → investigasi → CAPA. */
class Ncr extends Model
{
    use HasUuids;

    protected $table = 'mfg_ncrs';

    protected $fillable = [
        'number', 'source', 'inspection_id', 'production_order_id', 'supplier_id', 'lot_id',
        'severity', 'status', 'title', 'description', 'scar_ref', 'due_date', 'closed_at',
        'opened_by_user_id',
    ];

    protected $casts = [
        'inspection_id' => 'integer', 'due_date' => 'date', 'closed_at' => 'datetime',
        'opened_by_user_id' => 'integer',
    ];

    public function capas(): HasMany
    {
        return $this->hasMany(Capa::class, 'ncr_id');
    }
}
