<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class SybTradeAuditLog extends Model
{
    public const bool UPDATED_AT = false;

    protected $table = 'syb_trade_audit_logs';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'audit_ref',
        'event_name',
        'details',
        'shariah_compliance_status',
    ];
}
