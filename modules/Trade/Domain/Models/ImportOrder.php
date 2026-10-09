<?php

declare(strict_types=1);

namespace Modules\Trade\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ImportOrder extends Model
{
    use HasUuids;

    protected $table = 'trd_import_orders';

    protected $fillable = [
        'order_number',
        'supplier_name',
        'origin_country_code',
        'origin_port_id',
        'incoterm_code',
        'currency',
        'cif_foreign_amount',
        'cif_idr',
        'customs_duty_bm_idr',
        'import_vat_ppn_idr',
        'import_tax_pph22_idr',
        'total_landed_cost_idr',
        'pib_number',
        'coo_verified',
        'status',
    ];

    protected $casts = [
        'cif_foreign_amount' => 'integer',
        'cif_idr' => 'integer',
        'customs_duty_bm_idr' => 'integer',
        'import_vat_ppn_idr' => 'integer',
        'import_tax_pph22_idr' => 'integer',
        'total_landed_cost_idr' => 'integer',
        'coo_verified' => 'boolean',
    ];

    public function originCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'origin_country_code', 'code');
    }

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function incoterm(): BelongsTo
    {
        return $this->belongsTo(Incoterm::class, 'incoterm_code', 'code');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(TradeDocument::class, 'documentable');
    }
}
