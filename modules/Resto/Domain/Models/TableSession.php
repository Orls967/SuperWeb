<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Resto\Domain\Enums\SessionStatus;

class TableSession extends Model
{
    protected $table = 'resto_table_sessions';

    protected $fillable = [
        'uuid',
        'outlet_id',
        'table_id',
        'opened_by',
        'guest_count',
        'opened_at',
        'closed_at',
        'status',
    ];

    protected $casts = [
        'status' => SessionStatus::class,
        'guest_count' => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TableSession $session) {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestoTable::class, 'table_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_session_id');
    }

    public function activeOrder()
    {
        return $this->hasOne(Order::class, 'table_session_id')->whereIn('status', ['open', 'awaiting_payment'])->latestOfMany();
    }
}
