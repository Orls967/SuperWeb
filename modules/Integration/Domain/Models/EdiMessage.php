<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EdiMessage extends Model
{
    protected $table = 'intg_edi_messages';

    protected $fillable = [
        'control_number',
        'edi_standard',
        'transaction_set',
        'sender_id',
        'receiver_id',
        'raw_message',
        'functional_status',
    ];
}
