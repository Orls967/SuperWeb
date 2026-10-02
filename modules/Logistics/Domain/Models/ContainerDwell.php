<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerDwell extends LogisticsEntity
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'lgx_container_dwells';

    protected $fillable = [
        'container_id',
        'shipment_id',
        'shipper_id',
        'location_id',
        'tariff_id',
        'kind',
        'status',
        'started_at',
        'ended_at',
        'billable_days',
        'accrued_amount_idr',
        'last_accrued_on',
        'invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'billable_days' => 'integer',
            'accrued_amount_idr' => 'integer',
            'last_accrued_on' => 'date',
        ];
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'container_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(DdTariff::class, 'tariff_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(LogisticsInvoice::class, 'invoice_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
