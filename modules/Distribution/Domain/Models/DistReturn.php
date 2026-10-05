<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Retur & klaim distributor (43.5): kebijakan per kontrak, kredit nota. */
class DistReturn extends Model
{
    use HasUuids;

    protected $table = 'dist_returns';

    protected $fillable = [
        'number', 'distributor_id', 'order_id', 'reason', 'disposition', 'qty', 'amount_idr',
        'status', 'credit_note_invoice_id', 'evidence_note', 'requested_at', 'requested_by_user_id',
    ];

    protected $casts = [
        'order_id' => 'string', 'qty' => 'decimal:6', 'amount_idr' => 'integer',
        'credit_note_invoice_id' => 'string', 'requested_at' => 'date', 'requested_by_user_id' => 'integer',
    ];

    public const REASONS = ['expired', 'damaged', 'wrong_ship', 'overstock', 'quality'];

    public const DISPOSITIONS = ['restock', 'quarantine', 'destroy'];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }
}
