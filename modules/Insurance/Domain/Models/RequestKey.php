<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Insurance\Domain\Concerns\AppendOnly;

class RequestKey extends Model
{
    use AppendOnly;

    protected $table = 'ins_request_keys';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'idempotency_key',
        'scope',
        'payload_hash',
        'created_by',
    ];
}
