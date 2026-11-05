<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Party\Contracts\Party;

class SiuInvestigation extends Model
{
    protected $table = 'ins_siu_investigations';

    /** @var list<string> */
    protected $fillable = [
        'case_number',
        'fraud_assessment_id',
        'subject_party_id',
        'reason',
        'status',
        'investigator_findings',
        'decision_notes',
        'approval_ref',
        'assigned_to',
        'decided_by',
        'decided_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'approval_ref' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FraudScore, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(FraudScore::class, 'fraud_assessment_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'subject_party_id');
    }

    /** @return BelongsTo<User, $this> */
    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
