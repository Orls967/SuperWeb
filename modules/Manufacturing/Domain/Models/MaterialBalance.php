<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Posisi stok pabrik per material: on-hand vs dipesan (sumber netting MRP). */
class MaterialBalance extends Model
{
    use HasUuids;

    protected $table = 'mfg_material_balances';

    protected $fillable = ['material_id', 'qty_on_hand', 'qty_reserved'];

    protected $casts = ['qty_on_hand' => 'decimal:6', 'qty_reserved' => 'decimal:6'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function qtyAvailable(): string
    {
        return bcsub((string) $this->qty_on_hand, (string) $this->qty_reserved, 6);
    }
}
