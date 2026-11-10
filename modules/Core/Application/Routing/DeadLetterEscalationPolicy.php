<?php

declare(strict_types=1);

namespace Modules\Core\Application\Routing;

use Modules\Core\Domain\Models\CoreEventDelivery;

/**
 * Kebijakan Eskalasi Insiden Dead-Letter Queue (DeadLetterEscalationPolicy)
 *
 * Mengklasifikasikan tingkat keparahan dan batas waktu resolusi insiden DLQ
 * berdasarkan domain topik dan akumulasi waktu penundaan pesan.
 */
class DeadLetterEscalationPolicy
{
    public const ESCALATION_LEVEL_0 = 0; // Info / self-healing

    public const ESCALATION_LEVEL_1 = 1; // SRE Operational Review (SLA 4 jam)

    public const ESCALATION_LEVEL_2 = 2; // Domain Lead Escalation (SLA 1 jam)

    public const ESCALATION_LEVEL_3 = 3; // Emergency War Room (SLA 15 menit)

    /**
     * Mengevaluasi level eskalasi yang harus diterapkan pada rekaman DLQ.
     *
     * @return array{
     *     level: int,
     *     sla_minutes: int,
     *     priority: 'normal'|'high'|'urgent'|'critical',
     *     assigned_channel: string,
     *     instructions: string
     * }
     */
    public function evaluateEscalation(CoreEventDelivery $delivery): array
    {
        $topic = (string) ($delivery->event?->topic ?? '');
        $retryCount = (int) $delivery->retry_count;
        $failedDurationSeconds = $delivery->updated_at
            ? (time() - $delivery->updated_at->timestamp)
            : 0;

        // Level 3: Keuangan atau Pasien Rumah Sakit yang tertahan > 30 menit
        if ($this->isMissionCriticalDomain($topic)) {
            if ($retryCount >= 5 || $failedDurationSeconds > 1800) {
                return [
                    'level' => self::ESCALATION_LEVEL_3,
                    'sla_minutes' => 15,
                    'priority' => 'critical',
                    'assigned_channel' => 'incident_war_room',
                    'instructions' => 'Pesan domain misi-kritis tertahan! Buka war room darurat, notifikasi Tech Lead dan SRE on-call segera.',
                ];
            }

            return [
                'level' => self::ESCALATION_LEVEL_2,
                'sla_minutes' => 60,
                'priority' => 'urgent',
                'assigned_channel' => 'domain_leads_urgent',
                'instructions' => 'Kegagalan transaksi keuangan/medis terdeteksi. Mulai investigasi DLQ dalam waktu 1 jam.',
            ];
        }

        // Level 2: Logistik / Pengadaan tertahan > 2 jam
        if ($this->isCoreOperationsDomain($topic)) {
            if ($failedDurationSeconds > 7200) {
                return [
                    'level' => self::ESCALATION_LEVEL_2,
                    'sla_minutes' => 60,
                    'priority' => 'urgent',
                    'assigned_channel' => 'operations_support',
                    'instructions' => 'Operasi logistik/gudang tertunda > 2 jam. Eskalasi ke supervisor operasional.',
                ];
            }

            return [
                'level' => self::ESCALATION_LEVEL_1,
                'sla_minutes' => 240,
                'priority' => 'high',
                'assigned_channel' => 'sre_triage_queue',
                'instructions' => 'Kegagalan operasional logistik sedang ditinjau pada triase harian.',
            ];
        }

        // Level 1 / 0: Analitik / Telematika usang
        return [
            'level' => self::ESCALATION_LEVEL_1,
            'sla_minutes' => 1440, // 24 jam
            'priority' => 'normal',
            'assigned_channel' => 'daily_ops_digest',
            'instructions' => 'Pesan data analitik non-kritis. Discard otomatis diizinkan jika usang > 7 hari.',
        ];
    }

    protected function isMissionCriticalDomain(string $topic): bool
    {
        return str_starts_with($topic, 'finance.')
            || str_starts_with($topic, 'hospital.')
            || str_starts_with($topic, 'payment.');
    }

    protected function isCoreOperationsDomain(string $topic): bool
    {
        return str_starts_with($topic, 'logistics.')
            || str_starts_with($topic, 'procurement.')
            || str_starts_with($topic, 'inventory.')
            || str_starts_with($topic, 'banquet.');
    }
}
