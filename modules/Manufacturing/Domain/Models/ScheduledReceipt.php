<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penerimaan terjadwal (PO/supply) yang dipakai netting MRP. */
class ScheduledReceipt extends Model
{
    use HasUuids;

    protected $table = 'mfg_scheduled_receipts';

    protected $fillable = ['material_id', 'due_date', 'qty', 'source_type', 'source_ref', 'status'];

    protected $casts = ['due_date' => 'date', 'qty' => 'decimal:6'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
