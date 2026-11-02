<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class GateScanLog extends Model
{
    protected $table = 'ven_gate_scans';

    protected $fillable = [
        'ticket_id',
        'gate_code',
        'direction',
        'scan_result',
        'denial_reason',
        'scanned_by_user_id',
        'scanned_at',
    ];

    protected $casts = [
        'ticket_id' => 'integer',
        'scanned_by_user_id' => 'integer',
        'scanned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Log pemindaian gerbang bersifat append-only dan tidak boleh dihapus (PR5)');
        });

        static::updating(function (): void {
            throw new RuntimeException('Log pemindaian gerbang bersifat append-only dan tidak boleh diubah (PR5)');
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTicket::class, 'ticket_id');
    }
}
