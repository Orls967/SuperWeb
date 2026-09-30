<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Resto\Domain\Enums\ShiftStatus;

class Shift extends Model
{
    protected $table = 'resto_shifts';

    protected $fillable = [
        'uuid',
        'outlet_id',
        'cashier_id',
        'opened_at',
        'closed_at',
        'opening_float',
        'expected_cash',
        'counted_cash',
        'variance',
        'status',
        'note',
    ];

    protected $casts = [
        'status' => ShiftStatus::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_float' => 'integer',
        'expected_cash' => 'integer',
        'counted_cash' => 'integer',
        'variance' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Shift $shift) {
            if (empty($shift->uuid)) {
                $shift->uuid = (string) Str::uuid();
            }
        });
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === ShiftStatus::OPEN;
    }
}
