<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models\AgriProcessing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MfgAgriBatch extends Model
{
    protected $table = 'mfg_agri_batches';

    protected $fillable = [
        'batch_code',
        'target_product_sku',
        'planned_input_grams',
        'actual_output_grams',
        'waste_grams',
        'status',
        'protein_bps',
        'moisture_bps',
        'export_grade',
    ];

    protected $casts = [
        'planned_input_grams' => 'integer',
        'actual_output_grams' => 'integer',
        'waste_grams' => 'integer',
        'protein_bps' => 'integer',
        'moisture_bps' => 'integer',
    ];

    public function inputs(): HasMany
    {
        return $this->hasMany(MfgAgriBatchInput::class, 'mfg_agri_batch_id');
    }
}
