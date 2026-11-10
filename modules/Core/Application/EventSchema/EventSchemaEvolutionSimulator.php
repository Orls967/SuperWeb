<?php

declare(strict_types=1);

namespace Modules\Core\Application\EventSchema;

use Modules\Core\Application\Services\EventSchemaRegistry;
use Modules\Core\Domain\Algorithms\Events\SchemaAdditiveCompatibilityChecker;
use Modules\Core\Domain\Models\CoreEventSchema;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Simulator Evolusi Skema Event (EventSchemaEvolutionSimulator)
 *
 * Menguji apakah rancangan skema baru dapat memvalidasi sampel event historis
 * tanpa memutus alur konsumsi konsumen terdaftar.
 */
class EventSchemaEvolutionSimulator
{
    public function __construct(
        protected SchemaAdditiveCompatibilityChecker $compatibilityChecker,
        protected SchemaDiffViewer $diffViewer,
        protected EventSchemaRegistry $schemaRegistry
    ) {}

    /**
     * Menjalankan simulasi evolusi skema untuk topik tertentu.
     *
     * @param  array<string, mixed>  $proposedSchema
     * @return array{
     *     status: 'SAFE'|'WARNING_DEPRECATION'|'BREAKING_BLOCKED',
     *     compatibility_check: array{compatible: bool, violations: array<string>},
     *     diff_report: array<string, mixed>,
     *     historical_validation: array{
     *         total_sampled: int,
     *         passed_count: int,
     *         failed_count: int,
     *         failure_samples: array<int, array{event_id: string, errors: array<mixed>}>
     *     },
     *     consumer_impact: array<string, array{status: 'COMPATIBLE'|'AT_RISK', reason: string}>,
     *     recommendation: string
     * }
     */
    public function simulate(string $topic, array $proposedSchema, int $sampleSize = 50): array
    {
        // 1. Ambil skema aktif saat ini
        $latestRecord = CoreEventSchema::where('event_name', $topic)->latest('version')->first();
        $currentSchema = $latestRecord ? (array) $latestRecord->payload_schema : [];

        // 2. Periksa kompatibilitas aditif A6 jika skema sebelumnya ada
        $compatibilityViolations = [];
        $isCompatible = true;

        if (! empty($currentSchema)) {
            $rawViolations = $this->compatibilityChecker->checkCompatibility($currentSchema, $proposedSchema);
            $isCompatible = empty($rawViolations);
            foreach ($rawViolations as $v) {
                $compatibilityViolations[] = "[{$v['code']}] pada path '{$v['path']}'";
            }
        }

        // 3. Hitung selisih struktural (diff)
        $diff = $this->diffViewer->diff($currentSchema, $proposedSchema);

        // 4. Ambil sampel event historis dari tabel sim_event_spine
        $samples = SimEventSpine::query()
            ->where('topic', $topic)
            ->latest('id')
            ->take($sampleSize)
            ->get();

        $passedCount = 0;
        $failedCount = 0;
        $failureSamples = [];

        foreach ($samples as $event) {
            $payload = (array) $event->payload;
            $validation = $this->validatePayloadAgainstSchema($payload, $proposedSchema);

            if ($validation->isValid()) {
                $passedCount++;
            } else {
                $failedCount++;
                if (count($failureSamples) < 5) {
                    $failureSamples[] = [
                        'event_id' => (string) ($event->event_id ?? $event->id),
                        'errors' => $validation->getErrors(),
                    ];
                }
            }
        }

        // 5. Analisis dampak terhadap konsumen terdaftar
        $consumerImpact = $this->analyzeConsumerImpact($topic, $diff);

        // 6. Tentukan status keselamatan dan rekomendasi
        $status = 'SAFE';
        $recommendation = 'Skema baru memenuhi kaidah aditif A6 dan lulus validasi sampel historis. Aman untuk didaftarkan.';

        if (! $isCompatible || $diff['is_breaking'] || $failedCount > 0) {
            $status = 'BREAKING_BLOCKED';
            $recommendation = 'DILARANG DEPLOY: Usulan skema mengandung breaking change atau gagal memvalidasi data historis.';
        } elseif (! empty($diff['enum_changes']) || ! empty($diff['added_properties'])) {
            $status = 'WARNING_DEPRECATION';
            $recommendation = 'Skema kompatibel secara aditif. Pastikan modul konsumen memperbarui pustaka pembaca untuk memanfaatkan field baru.';
        }

        return [
            'status' => $status,
            'compatibility_check' => [
                'compatible' => $isCompatible,
                'violations' => $compatibilityViolations,
            ],
            'diff_report' => $diff,
            'historical_validation' => [
                'total_sampled' => $samples->count(),
                'passed_count' => $passedCount,
                'failed_count' => $failedCount,
                'failure_samples' => $failureSamples,
            ],
            'consumer_impact' => $consumerImpact,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Memvalidasi sampel payload terhadap skema usulan.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $schema
     */
    protected function validatePayloadAgainstSchema(array $payload, array $schema): EventSchemaValidationResult
    {
        $errors = [];
        $required = (array) ($schema['required'] ?? []);
        $properties = (array) ($schema['properties'] ?? []);

        // Cek required properties
        foreach ($required as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null) {
                $errors[] = [
                    'path' => $field,
                    'message' => "Field wajib '{$field}' tidak ditemukan pada payload sampel.",
                    'rule' => 'required',
                ];
            }
        }

        // Cek tipe dasar
        foreach ($properties as $field => $def) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null) {
                continue;
            }

            $expectedType = is_array($def) ? ($def['type'] ?? 'any') : 'any';
            $actualValue = $payload[$field];

            if ($expectedType === 'string' && ! is_string($actualValue)) {
                $errors[] = ['path' => $field, 'message' => 'Harus string, ditemukan '.gettype($actualValue), 'rule' => 'type'];
            } elseif (($expectedType === 'integer' || $expectedType === 'number') && ! is_numeric($actualValue)) {
                $errors[] = ['path' => $field, 'message' => 'Harus numerik, ditemukan '.gettype($actualValue), 'rule' => 'type'];
            } elseif ($expectedType === 'boolean' && ! is_bool($actualValue)) {
                $errors[] = ['path' => $field, 'message' => 'Harus boolean, ditemukan '.gettype($actualValue), 'rule' => 'type'];
            } elseif ($expectedType === 'array' && ! is_array($actualValue)) {
                $errors[] = ['path' => $field, 'message' => 'Harus array, ditemukan '.gettype($actualValue), 'rule' => 'type'];
            }
        }

        return empty($errors) ? EventSchemaValidationResult::pass() : EventSchemaValidationResult::fail($errors);
    }

    /**
     * @param  array<string, mixed>  $diff
     * @return array<string, array{status: 'COMPATIBLE'|'AT_RISK', reason: string}>
     */
    protected function analyzeConsumerImpact(string $topic, array $diff): array
    {
        $impact = [];
        $consumers = [
            'gl_posting_engine',
            'inventory_sync_service',
            'notification_broker',
            'telematics_archive',
        ];

        foreach ($consumers as $consumer) {
            if ($diff['is_breaking']) {
                $impact[$consumer] = [
                    'status' => 'AT_RISK',
                    'reason' => 'Perubahan skema bersifat breaking, berpotensi menggagalkan pembacaan oleh konsumen.',
                ];
            } else {
                $impact[$consumer] = [
                    'status' => 'COMPATIBLE',
                    'reason' => 'Perubahan bersifat aditif penuh, konsumen dapat melanjutkan konsumsi tanpa intervensi.',
                ];
            }
        }

        return $impact;
    }
}
