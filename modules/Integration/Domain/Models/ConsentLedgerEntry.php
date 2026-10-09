<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentLedgerEntry extends Model
{
    protected $table = 'sec_consent_ledger';

    protected $fillable = [
        'subject_id',
        'line_code',
        'purpose_code',
        'status',
        'consented_at',
        'withdrawn_at',
        'legal_basis',
        'notes',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public const STATUS_OPTED_IN = 'OPTED_IN';

    public const STATUS_OPTED_OUT = 'OPTED_OUT';

    public const STATUS_WITHDRAWN = 'WITHDRAWN';

    public function isConsented(): bool
    {
        return $this->status === self::STATUS_OPTED_IN;
    }
}
