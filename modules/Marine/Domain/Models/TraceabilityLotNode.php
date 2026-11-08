<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraceabilityLotNode extends Model
{
    protected $table = 'mar_traceability_lot_nodes';

    protected $fillable = [
        'node_uuid',
        'export_lot_id',
        'leaf_index',
        'hatchery_data_hash',
        'feed_cycle_hash',
        'water_quality_hash',
        'harvest_event_hash',
        'assay_lab_hash',
        'combined_leaf_hash',
        'merkle_root_hash',
        'merkle_proof_path',
        'anchored_at',
    ];

    protected $casts = [
        'merkle_proof_path' => 'array',
    ];

    public function exportLot(): BelongsTo
    {
        return $this->belongsTo(AquacultureExportLot::class, 'export_lot_id');
    }
}
