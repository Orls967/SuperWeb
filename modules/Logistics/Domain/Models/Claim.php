<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Claim extends LogisticsEntity
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    public const TYPES = ['damage', 'loss', 'delay'];

    protected $table = 'lgx_claims';

    protected $fillable = [
        'claim_number',
        'shipment_id',
        'claim_type',
        'insured',
        'claimed_amount_idr',
        'cap_amount_idr',
        'approved_amount_idr',
        'description',
        'status',
        'active_key',
        'created_by',
        'submitted_by',
        'submitted_at',
        'decided_by',
        'decided_at',
        'decision_notes',
        'paid_amount_idr',
        'paid_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'insured' => 'boolean',
            'claimed_amount_idr' => 'integer',
            'cap_amount_idr' => 'integer',
            'approved_amount_idr' => 'integer',
            'paid_amount_idr' => 'integer',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
