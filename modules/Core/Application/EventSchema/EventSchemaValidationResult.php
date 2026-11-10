<?php

declare(strict_types=1);

namespace Modules\Core\Application\EventSchema;

/**
 * Nilai Hasil Validasi Skema Event (EventSchemaValidationResult)
 *
 * Mengenkapsulasi status kelulusan validasi payload terhadap JSON Schema kanonikal,
 * memuat daftar galat spesifik, peringatan (warnings), dan properti terdepresiasi.
 */
class EventSchemaValidationResult
{
    /**
     * @param  bool  $isValid
     * @param  array<int, array{path: string, message: string, rule: string}>  $errors
     * @param  array<int, array{path: string, message: string}>  $warnings
     * @param  array<int, string>  $deprecatedFields
     */
    public function __construct(
        protected bool $isValid,
        protected array $errors = [],
        protected array $warnings = [],
        protected array $deprecatedFields = []
    ) {}

    public static function pass(): self
    {
        return new self(isValid: true);
    }

    /**
     * @param  array<int, array{path: string, message: string, rule: string}>  $errors
     */
    public static function fail(array $errors): self
    {
        return new self(isValid: false, errors: $errors);
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * @return array<int, array{path: string, message: string, rule: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<int, array{path: string, message: string}>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array<int, string>
     */
    public function getDeprecatedFields(): array
    {
        return $this->deprecatedFields;
    }

    public function addWarning(string $path, string $message): self
    {
        $this->warnings[] = [
            'path' => $path,
            'message' => $message,
        ];

        return $this;
    }

    public function addDeprecatedField(string $fieldName): self
    {
        if (! in_array($fieldName, $this->deprecatedFields, true)) {
            $this->deprecatedFields[] = $fieldName;
        }

        return $this;
    }

    /**
     * Mengembalikan pesan galat pertama yang ditemukan atau null jika valid.
     */
    public function getFirstErrorMessage(): ?string
    {
        if (empty($this->errors)) {
            return null;
        }

        $first = $this->errors[0];

        return "{$first['path']}: {$first['message']}";
    }

    /**
     * Mengembalikan representasi array lengkap untuk respons API atau log audit.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'deprecated_fields' => $this->deprecatedFields,
        ];
    }
}
