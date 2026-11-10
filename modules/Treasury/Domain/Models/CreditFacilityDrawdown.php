<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditFacilityDrawdown extends Model
{
    use HasUuids;

    protected $table = 'trs_credit_facility_drawdowns';

    protected $fillable = [
        'facility_id',
        'entity_id',
        'draw_date',
        'amount_idr',
        'covenant_der_permil',
        'max_der_permil',
        'verified_debt_idr',
        'verified_equity_idr',
        'ledger_tx_id',
        'created_by',
    ];

    protected $casts = [
        'draw_date' => 'date',
        'amount_idr' => 'integer',
        'covenant_der_permil' => 'integer',
        'max_der_permil' => 'integer',
        'verified_debt_idr' => 'integer',
        'verified_equity_idr' => 'integer',
        'ledger_tx_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(CreditFacility::class, 'facility_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
