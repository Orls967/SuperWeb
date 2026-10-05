<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EdiMessage extends Model
{
    protected $table = 'intg_edi_messages';

    protected $guarded = [];
}
