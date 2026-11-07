<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricCredential extends Model
{
    protected $table = 'ven_biometric_credentials';

    protected $fillable = [
        'credential_code',
        'user_id',
        'biometric_template_hash',
        'liveness_signature_hash',
        'liveness_score_bps',
        'sensor_model',
        'status',
        'consented_at',
        'revoked_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'liveness_score_bps' => 'integer',
        'consented_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getTemplateHashAttribute(): ?string
    {
        return $this->biometric_template_hash;
    }

    public function setTemplateHashAttribute(?string $value): void
    {
        $this->attributes['biometric_template_hash'] = $value;
    }

    public function getConsentGivenAtAttribute(): ?Carbon
    {
        return $this->consented_at;
    }

    public function setConsentGivenAtAttribute($value): void
    {
        $this->attributes['consented_at'] = $value;
    }
}
