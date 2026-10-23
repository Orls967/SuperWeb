<?php

declare(strict_types=1);

namespace Modules\Banking\Contracts;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Domain\Traits\HasUuid;

/**
 * Cross-module contract for LedgerTransaction model.
 */
class LedgerTransaction extends Model
{
    use HasUuid;

    protected $table = 'bank_ledger_transactions';

    protected $fillable = [
        'uuid',
        'type',
        'reference_type',
        'reference_id',
        'description',
        'idempotency_key',
        'posted_at',
        'created_by',
    ];
}
