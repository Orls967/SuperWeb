<?php

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class BiometricCredential extends Model
{
    protected $table = 'ven_biometric_credentials';

    protected $fillable = [
        'credential_code',
        'user_id',
        'biometric_template_hash',
        'liveness_signature_hash',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
