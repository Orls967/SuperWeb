<?php

declare(strict_types=1);

namespace Modules\Core\Application\EventSchema;

/**
 * Generator Draf Skema Event (SchemaDraftGenerator)
 *
 * Menginspeksi payload sampel array untuk menghasilkan struktur draf JSON Schema kanonikal:
 * tipe data, properti terurut, batasan required, dan deteksi enum otomatis.
 */
class SchemaDraftGenerator
{
    /**
     * Menghasilkan draf JSON Schema dari sampel array payload.
     *
     * @param  array<string, mixed>  $samplePayload
     * @return array<string, mixed>
     */
    public function generateDraft(array $samplePayload, array $requiredFields = []): array
    {
        $properties = [];

        foreach ($samplePayload as $key => $value) {
            $properties[$key] = $this->inferPropertySchema($value);
        }

        // Urutkan properti secara leksikografis
        ksort($properties);

        // Jika requiredFields tidak ditentukan, jadikan field non-null sebagai default required
        if (empty($requiredFields)) {
            $requiredFields = array_keys(array_filter($samplePayload, fn ($v) => $v !== null));
        }

        sort($requiredFields);

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_values(array_unique($requiredFields)),
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    protected function inferPropertySchema(mixed $value): array
    {
        if (is_int($value)) {
            return [
                'type' => 'integer',
                'minimum' => min(0, $value),
            ];
        }

        if (is_float($value)) {
            return [
                'type' => 'number',
            ];
        }

        if (is_bool($value)) {
            return [
                'type' => 'boolean',
            ];
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return [
                    'type' => 'array',
                ];
            }

            return [
                'type' => 'object',
                'properties' => $this->generateDraft($value)['properties'],
            ];
        }

        return [
            'type' => 'string',
        ];
    }
}
