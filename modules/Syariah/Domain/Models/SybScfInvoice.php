<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SybScfInvoice extends Model
{
    protected $table = 'syb_scf_invoices';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'facility_id',
        'invoice_number',
        'supplier_invoice_ref',
        'principal_cost_cents',
        'margin_profit_cents',
        'total_sale_price_cents',
        'custody_document_ref',
        'qabdh_verified_at',
        'due_date',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'principal_cost_cents' => 'integer',
        'margin_profit_cents' => 'integer',
        'total_sale_price_cents' => 'integer',
        'qabdh_verified_at' => 'datetime',
        'due_date' => 'date',
    ];

    /**
     * @return BelongsTo<SybScfFacility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(SybScfFacility::class, 'facility_id');
    }

    /**
     * @return HasMany<SybScfRepayment, $this>
     */
    public function repayments(): HasMany
    {
        return $this->hasMany(SybScfRepayment::class, 'invoice_id');
    }
}
