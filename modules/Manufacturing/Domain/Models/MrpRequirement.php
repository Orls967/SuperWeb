<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ringkasan kebutuhan bruto, penerimaan, stok proyeksi dan usulan per bucket. */
class MrpRequirement extends Model
{
    public $timestamps = false;

    protected $table = 'mfg_mrp_requirements';

    protected $fillable = [
        'run_id', 'material_id', 'period_start', 'gross_req',
        'scheduled_receipts', 'projected_on_hand', 'planned_order_qty', 'action',
    ];

    protected $casts = [
        'period_start' => 'date', 'gross_req' => 'decimal:6',
        'scheduled_receipts' => 'decimal:6', 'projected_on_hand' => 'decimal:6',
        'planned_order_qty' => 'decimal:6',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(MrpRun::class, 'run_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
