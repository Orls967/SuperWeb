<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetDemurrageInvoice extends Model
{
    protected $table = 'prt_fleet_demurrage_invoices';

    protected $fillable = [
        'laytime_calculation_id',
        'invoice_number',
        'bill_to_party',
        'amount_usd',
        'invoice_date',
        'due_date',
        'status',
        'dispute_reason',
    ];

    protected $casts = [
        'amount_usd' => 'float',
        'invoice_date' => 'date',
        'due_date' => 'date',
    ];

    public function laytimeCalculation(): BelongsTo
    {
        return $this->belongsTo(FleetLaytimeCalculation::class, 'laytime_calculation_id');
    }
}
