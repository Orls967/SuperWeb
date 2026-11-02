<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Shared\Domain\Traits\HasUuid;

class RetPackagingDeposit extends Model
{
    use HasUuid;

    protected $table = 'ret_packaging_deposits';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'deposit_code',
        'order_id',
        'customer_id',
        'package_code',
        'packaging_type',
        'deposit_amount_minor',
        'return_station_id',
        'inspected_condition',
        'status',
        'refunded_at',
        'forfeited_at',
        'forfeit_reason',
        'idempotency_key',
    ];

    protected $casts = [
        'deposit_amount_minor' => 'integer',
        'refunded_at' => 'datetime',
        'forfeited_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->deposit_code)) {
                $model->deposit_code = 'DEP-'.strtoupper(Str::random(10));
            }
            if (empty($model->packaging_type)) {
                $model->packaging_type = 'INSULATED_TOTE';
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(RetOrder::class, 'order_id');
    }
}
