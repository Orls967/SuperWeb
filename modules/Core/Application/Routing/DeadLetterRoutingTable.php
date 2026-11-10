<?php

declare(strict_types=1);

namespace Modules\Core\Application\Routing;

use Closure;
use Modules\Core\Domain\Models\CoreEventDelivery;

/**
 * Tabel Rute Khusus Dead-Letter Queue (DeadLetterRoutingTable)
 *
 * Mengarahkan pesan-pesan yang berulang kali gagal (dead lettered) ke penanganan spesifik:
 * - Karantina poison message
 * - Notifikasi darurat SRE / TaskInbox
 * - Discard otomatis untuk event transien kadaluwarsa
 * - Penjadwalan delayed retry bertahap
 */
class DeadLetterRoutingTable
{
    public const ACTION_QUARANTINE = 'QUARANTINE';
    public const ACTION_TASK_INBOX = 'TASK_INBOX';
    public const ACTION_AUTO_DISCARD = 'AUTO_DISCARD';
    public const ACTION_DELAYED_RETRY = 'DELAYED_RETRY';

    /**
     * @var array<int, array{rule: string, predicate: Closure, action: string, metadata: array<string, mixed>}>
     */
    protected array $rules = [];

    public function __construct()
    {
        $this->registerDefaultRules();
    }

    /**
     * Menambahkan aturan triase khusus ke tabel perutean DLQ.
     *
     * @param  Closure(CoreEventDelivery): bool  $predicate
     * @param  array<string, mixed>  $metadata
     */
    public function addRule(string $ruleName, Closure $predicate, string $action, array $metadata = []): self
    {
        $this->rules[] = [
            'rule' => $ruleName,
            'predicate' => $predicate,
            'action' => $action,
            'metadata' => $metadata,
        ];

        return $this;
    }

    /**
     * Mengevaluasi rekaman pengiriman gagal dan mengembalikan aksi tindakan yang direkomendasikan.
     *
     * @return array{rule: string, action: string, metadata: array<string, mixed>}
     */
    public function resolveAction(CoreEventDelivery $delivery): array
    {
        foreach ($this->rules as $entry) {
            $predicate = $entry['predicate'];
            if ($predicate($delivery)) {
                return [
                    'rule' => $entry['rule'],
                    'action' => $entry['action'],
                    'metadata' => $entry['metadata'],
                ];
            }
        }

        // Default jika tidak ada aturan khusus yang cocok: kirim ke TaskInbox operator
        return [
            'rule' => 'DEFAULT_OPERATOR_REVIEW',
            'action' => self::ACTION_TASK_INBOX,
            'metadata' => ['assigned_role' => 'spine_operator'],
        ];
    }

    /**
     * Mendaftarkan aturan evaluasi bawaan berdasarkan karakteristik galat.
     */
    protected function registerDefaultRules(): void
    {
        // 1. Poison message: syntax error, TypeError, JsonException
        $this->addRule(
            ruleName: 'FATAL_PAYLOAD_POISON',
            predicate: function (CoreEventDelivery $d): bool {
                $err = (string) $d->last_error;

                return str_contains($err, 'TypeError')
                    || str_contains($err, 'JsonException')
                    || str_contains($err, 'ParseError')
                    || str_contains($err, 'DivisionByZeroError');
            },
            action: self::ACTION_QUARANTINE,
            metadata: ['quarantine_reason' => 'Unrecoverable PHP type/syntax error in payload']
        );

        // 2. Data telematika GPS usang > 24 jam: auto discard
        $this->addRule(
            ruleName: 'STALE_TELEMETRY_DISCARD',
            predicate: function (CoreEventDelivery $d): bool {
                $topic = (string) ($d->event?->topic ?? '');
                $createdAt = $d->created_at ? $d->created_at->timestamp : 0;

                return str_starts_with($topic, 'telematics.') && (time() - $createdAt > 86400);
            },
            action: self::ACTION_AUTO_DISCARD,
            metadata: ['discard_reason' => 'Telemetry coordinate event older than 24 hours']
        );

        // 3. Masalah timeout jaringan atau deadlock database: delayed retry
        $this->addRule(
            ruleName: 'TRANSIENT_LOCK_TIMEOUT',
            predicate: function (CoreEventDelivery $d): bool {
                $err = (string) $d->last_error;

                return str_contains($err, 'Deadlock found')
                    || str_contains($err, 'Lock wait timeout')
                    || str_contains($err, 'Connection refused')
                    || str_contains($err, 'timed out');
            },
            action: self::ACTION_DELAYED_RETRY,
            metadata: ['backoff_multiplier' => 2, 'max_additional_retries' => 3]
        );
    }
}
