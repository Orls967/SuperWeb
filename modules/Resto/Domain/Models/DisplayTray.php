<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Resto\Domain\Enums\TrayStatus;

class DisplayTray extends Model
{
    public const int MAX_RECIRCULATION = 3;

    public const int MAX_DISPLAY_HOURS = 6;

    protected $table = 'resto_display_trays';

    protected $fillable = [
        'uuid',
        'outlet_id',
        'batch_id',
        'menu_item_id',
        'portions_remaining',
        'recirculation_count',
        'placed_at',
        'expires_at',
        'status',
        'cost_per_portion',
    ];

    protected $casts = [
        'status' => TrayStatus::class,
        'portions_remaining' => 'integer',
        'recirculation_count' => 'integer',
        'cost_per_portion' => 'integer',
        'placed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DisplayTray $tray) {
            if (empty($tray->uuid)) {
                $tray->uuid = (string) Str::uuid();
            }
        });
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function isExpired(): bool
    {
        return now()->greaterThanOrEqualTo($this->expires_at);
    }

    /**
     * Checks if tray can return to display from table service.
     * Recirculation count must be strictly less than MAX_RECIRCULATION (so it can become at most MAX_RECIRCULATION),
     * and time elapsed from cooked_at (or placed_at) must be strictly within MAX_DISPLAY_HOURS.
     */
    public function canRecirculate(): bool
    {
        if ($this->recirculation_count >= self::MAX_RECIRCULATION) {
            return false;
        }

        $baseTime = $this->batch?->cooked_at ?? $this->placed_at;
        $hoursElapsed = Carbon::parse($baseTime)->diffInHours(now(), false);

        if ($hoursElapsed >= self::MAX_DISPLAY_HOURS) {
            return false;
        }

        return $this->status === TrayStatus::IN_SERVICE || $this->status === TrayStatus::RETURNED;
    }

    public function totalWasteValue(): int
    {
        return (int) ($this->portions_remaining * $this->cost_per_portion);
    }
}
