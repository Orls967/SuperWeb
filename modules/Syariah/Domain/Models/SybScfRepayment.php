<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybScfRepayment extends Model
{
    protected $table = 'syb_scf_repayments';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_id',
        'repayment_reference',
        'amount_paid_cents',
        'principal_portion_cents',
        'margin_portion_cents',
        'paid_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount_paid_cents' => 'integer',
        'principal_portion_cents' => 'integer',
        'margin_portion_cents' => 'integer',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<SybScfInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SybScfInvoice::class, 'invoice_id');
    }
}
