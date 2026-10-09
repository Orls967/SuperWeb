<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Faktur penjualan distributor + nomor seri pajak (SIMULASI, PPN 11%). */
class DistInvoice extends Model
{
    use HasUuids;

    protected $table = 'dist_invoices';

    protected $fillable = [
        'number', 'tax_serial', 'order_id', 'distributor_id', 'subtotal_idr',
        'discount_idr', 'ppn_idr', 'total_idr', 'status', 'issued_at', 'notes', 'created_by_user_id',
    ];

    protected $casts = [
        'subtotal_idr' => 'integer', 'discount_idr' => 'integer', 'ppn_idr' => 'integer',
        'total_idr' => 'integer', 'issued_at' => 'date', 'created_by_user_id' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(DistOrder::class, 'order_id');
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }
}
