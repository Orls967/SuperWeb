<?php

declare(strict_types=1);

namespace Modules\Party\Contracts;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for Party model.
 */
class Party extends Model
{
    use HasUuids;

    protected $table = 'pty_parties';
}
