<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthClaim extends Model
{
    protected $table = 'ins_lh_health_claims';

    /** @var list<string> */
    protected $fillable = [
        'claim_number',
        'health_policy_id',
        'guarantee_letter_id',
        'claim_type',
        'incurred_amount_idr',
        'approved_amount_idr',
        'patient_share_idr',
        'status',
        'denial_code',
        'adjudication_notes',
        'settled_ledger_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'health_policy_id' => 'integer',
            'guarantee_letter_id' => 'integer',
            'incurred_amount_idr' => 'integer',
            'approved_amount_idr' => 'integer',
            'patient_share_idr' => 'integer',
            'settled_ledger_id' => 'integer',
        ];
    }

    /** @return BelongsTo<HealthPolicy, $this> */
    public function healthPolicy(): BelongsTo
    {
        return $this->belongsTo(HealthPolicy::class, 'health_policy_id');
    }

    /** @return BelongsTo<GuaranteeLetter, $this> */
    public function guaranteeLetter(): BelongsTo
    {
        return $this->belongsTo(GuaranteeLetter::class, 'guarantee_letter_id');
    }
}
