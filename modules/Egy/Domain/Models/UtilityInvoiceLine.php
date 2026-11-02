<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityInvoiceLine extends Model
{
    protected $table = 'egy_utility_invoice_lines';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'invoice_id',
        'window_type',
        'wh_consumed',
        'rate_per_kwh_minor',
        'subtotal_minor',
        'line_total_minor',
    ];

    protected $casts = [
        'wh_consumed' => 'integer',
        'rate_per_kwh_minor' => 'integer',
        'subtotal_minor' => 'integer',
        'line_total_minor' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(UtilityConsolidatedInvoice::class, 'invoice_id');
    }
}
