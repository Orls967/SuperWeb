<?php

declare(strict_types=1);

namespace Modules\Party\Contracts;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for LegalEntity model.
 */
class LegalEntity extends Model
{
    use HasUuids;

    protected $table = 'pty_legal_entities';
}
