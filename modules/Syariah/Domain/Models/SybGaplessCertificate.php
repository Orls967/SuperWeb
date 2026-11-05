<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybGaplessCertificate extends Model
{
    protected $table = 'syb_gapless_certificates';

    protected $fillable = [
        'certificate_number',
        'certificate_type',
        'recipient_user_id',
        'amount_idr',
        'hash_signature',
        'issued_at',
    ];

    protected $casts = [
        'recipient_user_id' => 'integer',
        'amount_idr' => 'integer',
        'issued_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}

