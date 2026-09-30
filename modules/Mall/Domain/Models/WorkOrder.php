<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mall\Domain\Enums\WorkOrderPriority;
use Modules\Mall\Domain\Enums\WorkOrderStatus;
use Modules\Mall\Domain\Enums\WorkOrderType;
use Modules\Shared\Domain\Traits\HasUuid;

class WorkOrder extends Model
{
    use HasUuid;

    protected $table = 'mall_work_orders';

    protected $fillable = [
        'uuid',
        'property_id',
        'asset_id',
        'unit_id',
        'tenant_id',
        'order_number',
        'type',
        'priority',
        'title',
        'description',
        'status',
        'reported_by_user_id',
        'assigned_to_user_id',
        'due_date',
        'sla_hours',
        'sla_breached',
        'parts_cost',
        'labor_cost',
        'total_cost',
        'is_billable_to_tenant',
        'billed_invoice_id',
        'completed_at',
    ];

    protected $casts = [
        'type' => WorkOrderType::class,
        'priority' => WorkOrderPriority::class,
        'status' => WorkOrderStatus::class,
        'due_date' => 'datetime',
        'sla_hours' => 'integer',
        'sla_breached' => 'boolean',
        'parts_cost' => 'integer',
        'labor_cost' => 'integer',
        'total_cost' => 'integer',
        'is_billable_to_tenant' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function billedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'billed_invoice_id');
    }

    public function checkSlaBreach(?Carbon $atTime = null): bool
    {
        if ($this->sla_breached) {
            return true;
        }

        $now = $atTime ?? Carbon::now();
        $due = $this->due_date ?? $this->created_at->addHours($this->sla_hours);

        if ($this->status !== WorkOrderStatus::COMPLETED && $now->isAfter($due)) {
            $this->update(['sla_breached' => true]);

            return true;
        }

        return false;
    }
}
