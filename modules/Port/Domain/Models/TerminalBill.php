<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalBill extends Model
{
    protected $table = 'prt_terminal_bills';

    protected $fillable = [
        'bill_number',
        'vessel_call_id',
        'line_party_id',
        'amount_idr',
        'thc_amount_idr',
        'dockage_amount_idr',
        'storage_amount_idr',
        'dnd_amount_idr',
        'ledger_journal_id',
        'status',
    ];

    protected $casts = [
        'line_party_id' => 'integer',
        'amount_idr' => 'integer',
        'thc_amount_idr' => 'integer',
        'dockage_amount_idr' => 'integer',
        'storage_amount_idr' => 'integer',
        'dnd_amount_idr' => 'integer',
        'ledger_journal_id' => 'integer',
    ];

    public function vesselCall(): BelongsTo
    {
        return $this->belongsTo(VesselCall::class, 'vessel_call_id');
    }
}
