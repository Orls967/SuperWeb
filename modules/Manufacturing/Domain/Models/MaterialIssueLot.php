<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Alokasi lot spesifik untuk issue FIFO/FEFO yang dapat direkonsiliasi. */
class MaterialIssueLot extends Model
{
    public $timestamps = false;

    protected $table = 'mfg_material_issue_lots';

    protected $fillable = ['material_issue_id', 'lot_id', 'qty'];

    protected $casts = ['qty' => 'decimal:6'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(MaterialIssue::class, 'material_issue_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'lot_id');
    }
}
