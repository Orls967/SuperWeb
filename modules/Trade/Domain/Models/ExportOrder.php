<?php

declare(strict_types=1);

namespace Modules\Trade\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ExportOrder extends Model
{
    use HasUuids;

    protected $table = 'trd_export_orders';

    protected $fillable = [
        'order_number',
        'buyer_name',
        'destination_country_code',
        'destination_port_id',
        'incoterm_code',
        'currency',
        'total_foreign_amount',
        'total_functional_idr',
        'peb_number',
        'status',
        'risk_transferred_at',
    ];

    protected $casts = [
        'total_foreign_amount' => 'integer',
        'total_functional_idr' => 'integer',
        'risk_transferred_at' => 'datetime',
    ];

    public function destinationCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'destination_country_code', 'code');
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
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
