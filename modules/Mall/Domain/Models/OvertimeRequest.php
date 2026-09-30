<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mall\Domain\Enums\OvertimeStatus;
use Modules\Shared\Domain\Traits\HasUuid;

class OvertimeRequest extends Model
{
    use HasUuid;

    protected $table = 'mall_overtime_requests';

    protected $fillable = [
        'uuid',
        'lease_id',
        'date',
        'start_time',
        'end_time',
        'hours',
        'rate_per_hour',
        'total_cost',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'invoice_id',
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'float',
        'rate_per_hour' => 'integer',
        'total_cost' => 'integer',
        'status' => OvertimeStatus::class,
        'approved_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
