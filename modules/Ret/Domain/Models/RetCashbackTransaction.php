<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Shared\Domain\Traits\HasUuid;

class RetCashbackTransaction extends Model
{
    use HasUuid;

    protected $table = 'ret_cashback_transactions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'cashback_code',
        'customer_id',
        'order_id',
        'reference_id',
        'source_domain',
        'source_line',
        'amount_minor',
        'transaction_amount_minor',
        'cashback_pct',
        'cashback_earned_minor',
        'type',
        'status',
        'expires_at',
        'idempotency_key',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'transaction_amount_minor' => 'integer',
        'cashback_earned_minor' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->cashback_code)) {
                $model->cashback_code = 'CB-'.strtoupper(Str::random(10));
            }
            if (empty($model->source_line)) {
                $model->source_line = 'RETAIL';
            }
            if ($model->transaction_amount_minor === null) {
                $model->transaction_amount_minor = $model->amount_minor ?? 0;
            }
            if ($model->cashback_pct === null) {
                $model->cashback_pct = 5.0;
            }
            if ($model->cashback_earned_minor === null) {
                $model->cashback_earned_minor = $model->amount_minor ?? 0;
            }
        });

        static::updating(function (self $model): void {
            throw new DomainException('Cashback transactions are append-only facts and cannot be updated.');
        });

        static::deleting(function (self $model): void {
            throw new DomainException('Cashback transactions are append-only facts and cannot be deleted.');
        });
    }
}
