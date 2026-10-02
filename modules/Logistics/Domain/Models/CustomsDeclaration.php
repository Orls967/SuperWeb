<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomsDeclaration extends LogisticsEntity
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_CLEARED = 'cleared';

    protected $table = 'lgx_customs_declarations';

    protected $fillable = [
        'declaration_number',
        'shipment_id',
        'type',
        'has_api',
        'status',
        'lane',
        'lines',
        'customs_value_idr',
        'bm_idr',
        'ppn_idr',
        'pph22_idr',
        'total_duty_idr',
        'hold_reason',
        'previous_shipment_status',
        'submitted_by',
        'submitted_at',
        'paid_by',
        'paid_at',
        'cleared_by',
        'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'has_api' => 'boolean',
            'lines' => 'array',
            'customs_value_idr' => 'integer',
            'bm_idr' => 'integer',
            'ppn_idr' => 'integer',
            'pph22_idr' => 'integer',
            'total_duty_idr' => 'integer',
            'submitted_at' => 'datetime',
            'paid_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
