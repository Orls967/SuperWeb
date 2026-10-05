<?php

declare(strict_types=1);

namespace Modules\Trade\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TradeDocument extends Model
{
    use HasUuids;

    protected $table = 'trd_trade_documents';

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'doc_type',
        'certificate_number',
        'issuing_authority',
        'issue_date',
        'valid_until',
        'is_verified',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'valid_until' => 'date',
        'is_verified' => 'boolean',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
