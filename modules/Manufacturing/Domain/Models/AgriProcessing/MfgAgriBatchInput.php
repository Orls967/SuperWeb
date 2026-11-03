<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models\AgriProcessing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfgAgriBatchInput extends Model
{
    protected $table = 'mfg_agri_batch_inputs';

    protected $fillable = [
        'mfg_agri_batch_id',
        'agri_collection_batch_id',
        'allocated_grams',
        'cost_idr',
    ];

    protected $casts = [
        'mfg_agri_batch_id' => 'integer',
        'allocated_grams' => 'integer',
        'cost_idr' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MfgAgriBatch::class, 'mfg_agri_batch_id');
    }
}
