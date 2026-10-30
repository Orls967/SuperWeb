<?php

declare(strict_types=1);

namespace Modules\Wealth\Domain\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only fact record for NAV oracle history (PR5).
 * Rejects updates and deletes.
 */
class WmNavHistory extends Model
{
    protected $table = 'wm_nav_history';

    public $timestamps = false;

    protected $fillable = [
        'asset_code',
        'asset_identifier',
        'nav_date',
        'nav_scale_4',
        'nav_per_unit_scale4',
        'source',
        'recorded_by',
        'idempotency_key',
        'created_at',
    ];

    protected $casts = [
        'nav_date' => 'date',
        'nav_scale_4' => 'integer',
        'nav_per_unit_scale4' => 'integer',
        'recorded_by' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('WmNavHistory is an immutable append-only fact and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new DomainException('WmNavHistory is an immutable append-only fact and cannot be deleted.');
        });
    }
}
