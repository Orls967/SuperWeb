<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Recall per lot: daftar penerima, kuarantina, penghancuran. */
class Recall extends Model
{
    use HasUuids;

    protected $table = 'mfg_recalls';

    protected $fillable = [
        'lot_id', 'ncr_id', 'reason', 'status', 'cost_idr', 'destruction_note',
        'started_at', 'completed_at', 'created_by_user_id',
    ];

    protected $casts = [
        'ncr_id' => 'integer', 'cost_idr' => 'integer', 'started_at' => 'datetime',
        'completed_at' => 'datetime', 'created_by_user_id' => 'integer',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'lot_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(RecallRecipient::class, 'recall_id');
    }
}
