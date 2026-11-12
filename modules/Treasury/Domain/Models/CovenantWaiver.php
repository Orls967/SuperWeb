<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CovenantWaiver extends Model
{
    use HasUuids;

    protected $table = 'trs_covenant_waivers';

    protected $fillable = [
        'facility_id',
        'waiver_code',
        'breached_der_permil',
        'waived_max_der_permil',
        'valid_until',
        'status',
        'approval_reference',
        'reason',
        'requested_by',
        'approved_by',
    ];

    protected $casts = [
        'breached_der_permil' => 'integer',
        'waived_max_der_permil' => 'integer',
        'valid_until' => 'date',
        'requested_by' => 'integer',
        'approved_by' => 'integer',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(CreditFacility::class, 'facility_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
