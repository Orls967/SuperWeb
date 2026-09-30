<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mall\Domain\Enums\VoucherStatus;
use Modules\Shared\Domain\Traits\HasUuid;

class Voucher extends Model
{
    use HasUuid;

    protected $table = 'mall_vouchers';

    protected $fillable = [
        'uuid',
        'voucher_code',
        'template_id',
        'user_id',
        'tenant_id',
        'nominal_value',
        'min_spend',
        'points_spent',
        'status',
        'expires_at',
        'used_at',
        'used_at_tenant_id',
        'used_transaction_ref',
        'settled_at',
        'settlement_id',
    ];

    protected $casts = [
        'nominal_value' => 'integer',
        'min_spend' => 'integer',
        'points_spent' => 'integer',
        'status' => VoucherStatus::class,
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(VoucherTemplate::class, 'template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function usedAtTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'used_at_tenant_id');
    }

    public function isValid(): bool
    {
        return $this->status === VoucherStatus::ACTIVE && $this->expires_at->isFuture();
    }
}
