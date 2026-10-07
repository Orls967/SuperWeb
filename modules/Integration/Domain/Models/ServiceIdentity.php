<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceIdentity extends Model
{
    protected $table = 'sec_service_identities';

    protected $fillable = [
        'service_name',
        'line_code',
        'sanctum_token_hash',
        'allowed_abilities',
        'device_trust_level',
        'mtls_cert_fingerprint',
        'cert_issued_at',
        'cert_expires_at',
        'is_active',
    ];

    protected $casts = [
        'allowed_abilities' => 'array',
        'is_active' => 'boolean',
        'cert_issued_at' => 'datetime',
        'cert_expires_at' => 'datetime',
    ];

    public function isCertValid(): bool
    {
        if ($this->cert_expires_at === null) {
            return true;
        }

        return now()->lt($this->cert_expires_at);
    }

    public function hasAbility(string $ability): bool
    {
        return in_array($ability, $this->allowed_abilities ?? [], true);
    }
}
