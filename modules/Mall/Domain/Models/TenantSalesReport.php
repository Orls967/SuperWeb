<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mall\Domain\Enums\SalesReportSource;
use Modules\Shared\Domain\Traits\HasUuid;

class TenantSalesReport extends Model
{
    use HasUuid;

    protected $table = 'mall_tenant_sales_reports';

    protected $fillable = [
        'uuid',
        'lease_id',
        'tenant_id',
        'period_month',
        'gross_sales',
        'net_sales',
        'transaction_count',
        'source',
        'reported_at',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'gross_sales' => 'integer',
        'net_sales' => 'integer',
        'transaction_count' => 'integer',
        'source' => SalesReportSource::class,
        'reported_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
