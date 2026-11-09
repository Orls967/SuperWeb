<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

/**
 * Anonimisasi data record dataset secara deterministik.
 */
class AnonymizeDatasetAction
{
    public function __construct(
        protected MaskPiiAction $masker = new MaskPiiAction()
    ) {
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function anonymizeRecord(string $domain, array $record): array
    {
        $sanitized = $record;

        foreach ($sanitized as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $sanitized[$key] = match (true) {
                str_contains($key, 'email') => $this->masker->maskEmail($value),
                str_contains($key, 'phone') || str_contains($key, 'telp') || str_contains($key, 'hp') => $this->masker->maskPhone($value),
                str_contains($key, 'nik') || str_contains($key, 'ktp') || str_contains($key, 'national_id') => $this->masker->maskNationalId($value),
                str_contains($key, 'address') || str_contains($key, 'alamat') => $this->masker->maskAddress($value),
                str_contains($key, 'account_number') || str_contains($key, 'rekening') => $this->masker->maskAccountNumber($value),
                default => $value,
            };
        }

        return $sanitized;
    }
}
