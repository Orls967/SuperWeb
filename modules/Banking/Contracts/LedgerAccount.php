<?php

declare(strict_types=1);

namespace Modules\Banking\Contracts;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Domain\Traits\HasUuid;

/**
 * Cross-module contract for LedgerAccount model.
 */
class LedgerAccount extends Model
{
    use HasUuid;

    protected $table = 'bank_ledger_accounts';

    protected $fillable = [
        'uuid',
        'code',
        'owner_type',
        'owner_id',
        'asset_code',
        'kind',
        'name',
        'allow_negative',
    ];
}
