<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Encrypted B2B webhook endpoint subscription with circuit breaker & 7-day rotation window (W8-D48).
 */
class CoreWebhook extends Model
{
    protected $table = 'core_webhooks';

    protected $fillable = [
        'tenant_id',
        'api_client_id',
        'code',
        'name',
        'target_url',
        'event_topics',
        'secret_encrypted',
        'previous_secret_encrypted',
        'consecutive_failures',
        'circuit_state',
        'circuit_opened_at',
        'is_active',
    ];

    protected $hidden = ['secret_encrypted', 'previous_secret_encrypted'];

    protected function casts(): array
    {
        return [
            'api_client_id' => 'integer',
            'event_topics' => 'array',
            'secret_encrypted' => 'encrypted',
            'previous_secret_encrypted' => 'encrypted',
            'previous_secret_expires_at' => 'datetime',
            'consecutive_failures' => 'integer',
            'circuit_opened_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(CoreWebhookDispatch::class, 'webhook_id');
    }

    /**
     * Active signing secrets including unexpired rotation overlap secret.
     *
     * @return list<string>
     */
    public function activeSecrets(\DateTimeInterface $now): array
    {
        $secrets = [(string) $this->secret_encrypted];

        if ($this->previous_secret_encrypted !== null
            && $this->previous_secret_encrypted !== ''
            && ($this->previous_secret_expires_at === null || $this->previous_secret_expires_at->getTimestamp() >= $now->getTimestamp())) {
            $secrets[] = (string) $this->previous_secret_encrypted;
        }

        return array_values(array_unique(array_filter($secrets)));
    }
}

