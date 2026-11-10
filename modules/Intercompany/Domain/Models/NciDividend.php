<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NciDividend extends Model
{
    protected $table = 'ic_nci_dividends';

    protected $fillable = [
        'subsidiary_nci_id',
        'subsidiary_entity_id',
        'declaration_date',
        'payment_date',
        'total_dividend_idr',
        'nci_dividend_idr',
        'nci_dividend_amount_idr',
        'status',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'total_dividend_idr' => 'integer',
        'nci_dividend_idr' => 'integer',
        'declaration_date' => 'date',
    ];

    public function subsidiaryEntity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'subsidiary_entity_id');
    }
}
