<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarineAquacultureLedgerEntry extends Model
{
    protected $table = 'mar_aquaculture_ledger_entries';

    protected $fillable = [
        'entry_code',
        'export_order_id',
        'batch_id',
        'account_code',
        'entry_type',
        'amount_minor_cents',
        'currency',
        'reference_type',
        'reference_id',
        'description',
        'paired_entry_code',
        'checksum_sha256',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(AquacultureExportOrder::class, 'export_order_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AquacultureBatch::class, 'batch_id');
    }
}
