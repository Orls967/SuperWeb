<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Packing list + label resi outbound (41.7). */
class PackingList extends Model
{
    use HasUuids;

    protected $table = 'wms_packing_lists';

    protected $fillable = [
        'number', 'transfer_id', 'appointment_id', 'items', 'tracking_number', 'status',
        'created_by_user_id',
    ];

    protected $casts = ['items' => 'array', 'appointment_id' => 'integer', 'created_by_user_id' => 'integer'];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class, 'transfer_id');
    }
}
