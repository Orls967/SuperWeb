<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Actions\MaskPiiAction;

/**
 * Pipeline reduksi dan penyamaran multi-tahap untuk data ekspor staging/sandbox.
 */
class PiiRedactionPipeline
{
    public function __construct(
        protected MaskPiiAction $masker = new MaskPiiAction,
        protected PiiVaultAnonymizer $vault = new PiiVaultAnonymizer
    ) {}

    /**
     * Memproses baris database mentah menjadi baris yang sepenuhnya aman dari bocoran PII
     *
     * @param  array<string, mixed>  $rawRow
     * @return array{sanitized: array<string, mixed>, blind_indexes: array<string, string>}
     */
    public function process(array $rawRow): array
    {
        $sanitized = $rawRow;
        $blindIndexes = [];

        foreach ($rawRow as $key => $val) {
            if (! is_string($val)) {
                continue;
            }

            $masked = $this->determineMaskedValue($key, $val);
            if ($masked !== null) {
                $sanitized[$key] = $masked;
                $blindIndexes[$key.'_bidx'] = $this->vault->generateBlindIndex($val);
            }
        }

        return [
            'sanitized' => $sanitized,
            'blind_indexes' => $blindIndexes,
        ];
    }

    private function determineMaskedValue(string $key, string $value): ?string
    {
        if (str_contains($key, 'email')) {
            return $this->masker->maskEmail($value);
        }

        if (str_contains($key, 'phone') || str_contains($key, 'telp')) {
            return $this->masker->maskPhone($value);
        }

        if (str_contains($key, 'nik') || str_contains($key, 'ktp')) {
            return $this->masker->maskNationalId($value);
        }

        return null;
    }
}
