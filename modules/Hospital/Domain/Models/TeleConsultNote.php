<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeleConsultNote extends Model
{
    protected $table = 'hsp_tele_consult_notes';

    protected $guarded = ['id'];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(TeleConsult::class, 'consultation_id');
    }
}
