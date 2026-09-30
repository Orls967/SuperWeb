<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Resto\Domain\Enums\StockCountStatus;

class StockCount extends Model
{
    protected $table = 'resto_stock_counts';

    protected $fillable = [
        'uuid',
        'outlet_id',
        'date',
        'status',
        'counted_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => StockCountStatus::class,
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockCount $count) {
            if (empty($count->uuid)) {
                $count->uuid = (string) Str::uuid();
            }
        });
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class, 'count_id');
    }
}
