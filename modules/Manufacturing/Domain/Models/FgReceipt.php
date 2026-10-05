<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penerimaan barang jadi (dan by-product) ke gudang + lot/serial. */
class FgReceipt extends Model
{
    use HasUuids;

    protected $table = 'mfg_fg_receipts';

    protected $fillable = [
        'production_order_id', 'material_id', 'qty', 'serials', 'by_product',
        'notes', 'received_by_user_id',
    ];

    protected $casts = [
        'qty' => 'decimal:6', 'serials' => 'array', 'by_product' => 'boolean',
        'received_by_user_id' => 'integer',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
