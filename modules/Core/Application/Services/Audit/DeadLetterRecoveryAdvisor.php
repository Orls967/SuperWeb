<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Audit;

use Modules\Core\Domain\Models\CoreEventDelivery;

/**
 * Penasihat Pemulihan Dead-Letter Queue (DeadLetterRecoveryAdvisor)
 *
 * Menganalisis pola pesan galat DLQ secara terperinci untuk mengkategorikan akar masalah
 * dan menyusun rencana tindakan remediasi yang dapat dieksekusi secara otomatis atau semi-otomatis.
 */
class DeadLetterRecoveryAdvisor
{
    public const CATEGORY_TRANSIENT_INFRA = 'TRANSIENT_INFRA';
    public const CATEGORY_SCHEMA_MISMATCH = 'SCHEMA_MISMATCH';
    public const CATEGORY_INTEGRITY_CONSTRAINT = 'INTEGRITY_CONSTRAINT';
    public const CATEGORY_BUSINESS_LOGIC_BUG = 'BUSINESS_LOGIC_BUG';
    public const CATEGORY_CORRUPTED_PAYLOAD = 'CORRUPTED_PAYLOAD';

    /**
     * Menganalisis satu pengiriman gagal dan mengembalikan diagnosa serta resep pemulihan.
     *
     * @return array{
     *     category: string,
     *     severity: 'LOW'|'MEDIUM'|'HIGH'|'CRITICAL',
     *     root_cause: string,
     *     recommended_action: 'REPLAY_NOW'|'RETRY_WITH_BACKOFF'|'QUARANTINE_POISON'|'SCHEMA_MIGRATION'|'DISCARD',
     *     explanation: string,
     *     remediation_steps: array<int, string>
     * }
     */
    public function diagnose(CoreEventDelivery $delivery): array
    {
        $error = (string) $delivery->last_error;
        $retryCount = (int) $delivery->retry_count;

        // 1. Transient / Database Lock / Network Timeout
        if ($this->isTransient($error)) {
            return [
                'category' => self::CATEGORY_TRANSIENT_INFRA,
                'severity' => 'MEDIUM',
                'root_cause' => 'Kegagalan sementara pada koneksi basis data atau batas waktu eksekusi.',
                'recommended_action' => 'RETRY_WITH_BACKOFF',
                'explanation' => 'Galat ini bersifat temporer dan memiliki peluang sembuh tinggi bila dicoba ulang setelah jeda backoff.',
                'remediation_steps' => [
                    'Pastikan koneksi database tidak mencapai batas max_connections.',
                    'Jadwalkan replay dengan algoritma eksponensial A5.',
                ],
            ];
        }

        // 2. Schema Mismatch / Missing Property
        if ($this->isSchemaMismatch($error)) {
            return [
                'category' => self::CATEGORY_SCHEMA_MISMATCH,
                'severity' => 'HIGH',
                'root_cause' => 'Inkompatibilitas skema payload event dengan ekspektasi fungsi konsumen.',
                'recommended_action' => 'SCHEMA_MIGRATION',
                'explanation' => 'Konsumen mengharapkan atribut tertentu yang tidak tersedia pada versi event ini.',
                'remediation_steps' => [
                    'Bandingkan versi skema produsen vs konsumen menggunakan SchemaDiffViewer.',
                    'Daftarkan skema aditif A6 baru atau lakukan patching toleran pada listener.',
                    'Lakukan replay setelah hotfix deployed.',
                ],
            ];
        }

        // 3. Database Integrity Constraint (Foreign Key / Unique)
        if ($this->isIntegrityConstraint($error)) {
            return [
                'category' => self::CATEGORY_INTEGRITY_CONSTRAINT,
                'severity' => 'HIGH',
                'root_cause' => 'Pelanggaran integritas referensial (Foreign key atau unique key violation).',
                'recommended_action' => 'QUARANTINE_POISON',
                'explanation' => 'Data induk yang dirujuk belum ada di tabel target, atau terjadi duplikasi kunci yang belum terselesaikan.',
                'remediation_steps' => [
                    'Periksa apakah ada event upstream yang tertunda atau belum terkirim.',
                    'Gunakan ScanAggregateGapsAction untuk memeriksa celah urutan.',
                ],
            ];
        }

        // 4. Fatal corrupted payload / Type error
        if ($this->isCorruptedPayload($error)) {
            return [
                'category' => self::CATEGORY_CORRUPTED_PAYLOAD,
                'severity' => 'CRITICAL',
                'root_cause' => 'Payload JSON rusak, tidak dapat didekode, atau memicu fatal type error.',
                'recommended_action' => 'QUARANTINE_POISON',
                'explanation' => 'Pesan beracun (poison message) yang tidak boleh diproses ulang berulang kali karena memblokir antrean.',
                'remediation_steps' => [
                    'Isolasi segera ke tabel core_poison_messages.',
                    'Buat regression unit test dengan sampel payload tersebut.',
                ],
            ];
        }

        // 5. Default: Business Logic Bug
        return [
            'category' => self::CATEGORY_BUSINESS_LOGIC_BUG,
            'severity' => $retryCount >= 5 ? 'HIGH' : 'MEDIUM',
            'root_cause' => 'Eksepsi logika bisnis tidak terduga di dalam handler konsumen.',
            'recommended_action' => $retryCount >= 5 ? 'QUARANTINE_POISON' : 'REPLAY_NOW',
            'explanation' => 'Terjadi RuntimeException atau DomainException yang membutuhkan penelaahan kode handler.',
            'remediation_steps' => [
                'Periksa jejak stack trace pada last_error.',
                'Tugaskan perbaikan ke tim domain penanggung jawab lewat TaskInbox.',
            ],
        ];
    }

    /**
     * Mendiagnosa kumpulan rekaman pengiriman DLQ dan mengelompokkan rekomendasi.
     *
     * @param  iterable<CoreEventDelivery>  $deliveries
     * @return array{
     *     total: int,
     *     by_category: array<string, int>,
     *     by_recommended_action: array<string, int>,
     *     diagnoses: array<int, array<string, mixed>>
     * }
     */
    public function batchDiagnose(iterable $deliveries): array
    {
        $byCategory = [];
        $byAction = [];
        $diagnoses = [];
        $total = 0;

        foreach ($deliveries as $delivery) {
            $diag = $this->diagnose($delivery);
            $total++;

            $cat = $diag['category'];
            $act = $diag['recommended_action'];

            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + 1;
            $byAction[$act] = ($byAction[$act] ?? 0) + 1;

            $diagnoses[] = array_merge(['delivery_id' => $delivery->id], $diag);
        }

        return [
            'total' => $total,
            'by_category' => $byCategory,
            'by_recommended_action' => $byAction,
            'diagnoses' => $diagnoses,
        ];
    }

    protected function isTransient(string $error): bool
    {
        return str_contains($error, 'Deadlock found')
            || str_contains($error, 'Lock wait timeout')
            || str_contains($error, 'Connection refused')
            || str_contains($error, 'timed out')
            || str_contains($error, 'Too many connections');
    }

    protected function isSchemaMismatch(string $error): bool
    {
        return str_contains($error, 'Undefined array key')
            || str_contains($error, 'Undefined index')
            || str_contains($error, 'SchemaValidationException')
            || str_contains($error, 'SchemaBreakingChangeException');
    }

    protected function isIntegrityConstraint(string $error): bool
    {
        return str_contains($error, 'Integrity constraint violation')
            || str_contains($error, 'FOREIGN KEY constraint failed')
            || str_contains($error, 'Duplicate entry')
            || str_contains($error, 'UNIQUE constraint failed');
    }

    protected function isCorruptedPayload(string $error): bool
    {
        return str_contains($error, 'TypeError')
            || str_contains($error, 'JsonException')
            || str_contains($error, 'Malformed UTF-8 characters')
            || str_contains($error, 'SyntaxError');
    }
}
