<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use InvalidArgumentException;
use Modules\Core\Domain\Algorithms\Events\SchemaAdditiveCompatibilityChecker;
use Modules\Core\Domain\Exceptions\SchemaBreakingChangeException;
use Modules\Core\Domain\Models\CoreEventSchema;

/**
 * Service EventSchemaRegistry (W8-D38)
 *
 * Mengelola pendaftaran skema event berversi dan menegakkan
 * aturan evolusi skema berbasis aditif murni (Algoritma A6).
 */
class EventSchemaRegistry
{
    public function __construct(
        protected ?SchemaAdditiveCompatibilityChecker $checker = null
    ) {
        $this->checker = $checker ?? new SchemaAdditiveCompatibilityChecker;
    }

    /**
     * Mendaftarkan skema event baru atau versi baru.
     * Wajib menolak versi baru yang melanggar aturan aditif A6 terhadap versi sebelumnya.
     *
     * @param  array<string, mixed>  $schemaDefinition
     *
     * @throws SchemaBreakingChangeException|InvalidArgumentException
     */
    public function registerSchema(
        string $eventName,
        int $version,
        array $schemaDefinition,
        ?string $description = null,
        string $producerModule = 'Core',
        array $consumerModules = ['*']
    ): CoreEventSchema {
        if ($version < 1) {
            throw new InvalidArgumentException("Versi skema event '{$eventName}' harus >= 1.");
        }

        // Cari versi sebelumnya jika ada (misal versi $version - 1 atau versi tertinggi < $version)
        $previous = CoreEventSchema::where('event_name', $eventName)
            ->where('version', '<', $version)
            ->orderBy('version', 'desc')
            ->first();

        if ($previous) {
            $prevSchema = is_array($previous->payload_schema) ? $previous->payload_schema : [];
            $violations = $this->checker->checkCompatibility($prevSchema, $schemaDefinition);

            if (! empty($violations)) {
                $violationList = implode(', ', array_map(fn ($v) => "{$v['path']} ({$v['code']})", $violations));

                throw new SchemaBreakingChangeException(
                    "Pendaftaran skema '{$eventName}' versi {$version} ditolak karena melanggar kompatibilitas aditif A6: {$violationList}",
                    $violations
                );
            }
        }

        return CoreEventSchema::updateOrCreate(
            [
                'event_name' => $eventName,
                'version' => $version,
            ],
            [
                'producer_module' => $producerModule,
                'consumer_modules' => $consumerModules,
                'payload_schema' => $schemaDefinition,
                'description' => $description,
            ]
        );
    }

    /**
     * Memeriksa keberadaan skema event.
     */
    public function hasSchema(string $eventName, int $version): bool
    {
        return CoreEventSchema::where('event_name', $eventName)
            ->where('version', $version)
            ->exists();
    }

    /**
     * Mengambil definisi skema event.
     *
     * @return array<string, mixed>|null
     */
    public function getSchema(string $eventName, int $version): ?array
    {
        $record = CoreEventSchema::where('event_name', $eventName)
            ->where('version', $version)
            ->first();

        return $record ? (array) $record->payload_schema : null;
    }

    /**
     * Validasi payload terhadap skema aktif.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidArgumentException
     */
    public function validate(string $eventName, int $version, array $payload): void
    {
        $schema = $this->getSchema($eventName, $version);
        if (! $schema) {
            // Jika skema belum didaftarkan di tabel, lewati atau izinkan
            return;
        }

        $required = (array) ($schema['required'] ?? []);
        foreach ($required as $field) {
            if (! array_key_exists($field, $payload)) {
                throw new InvalidArgumentException("Payload event '{$eventName}' v{$version} tidak memenuhi atribut wajib '{$field}'.");
            }
        }

        $properties = (array) ($schema['properties'] ?? []);
        foreach ($properties as $prop => $rule) {
            if (array_key_exists($prop, $payload)) {
                $val = $payload[$prop];
                $type = $rule['type'] ?? null;

                if ($type === 'string' && ! is_string($val)) {
                    throw new InvalidArgumentException("Field '{$prop}' harus bertipe string.");
                }
                if ($type === 'integer' && ! is_int($val)) {
                    throw new InvalidArgumentException("Field '{$prop}' harus bertipe integer.");
                }
                if ($type === 'number' && ! is_numeric($val)) {
                    throw new InvalidArgumentException("Field '{$prop}' harus bertipe number.");
                }
                if (isset($rule['enum']) && is_array($rule['enum']) && ! in_array($val, $rule['enum'], true)) {
                    throw new InvalidArgumentException("Nilai field '{$prop}' tidak diizinkan oleh enum.");
                }
                if (isset($rule['minimum']) && is_numeric($val) && $val < $rule['minimum']) {
                    throw new InvalidArgumentException("Nilai field '{$prop}' lebih kecil dari batasan minimum {$rule['minimum']}.");
                }
            }
        }
    }
}
