<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

final class ValidateSatuSehatFHIRComplianceAction
{
    /**
     * Memvalidasi format payload FHIR Bundle terhadap spesifikasi SatuSehat Kemenkes RI.
     *
     * @param  array<string, mixed>  $fhirBundleJson
     * @return array{
     *     is_valid: bool,
     *     compliance_score_pct: int,
     *     validation_errors: list<string>
     * }
     */
    public function execute(array $fhirBundleJson): array
    {
        $errors = [];

        if (($fhirBundleJson['resourceType'] ?? '') !== 'Bundle') {
            $errors[] = "Root resourceType harus bernilai 'Bundle'";
        }

        if (empty($fhirBundleJson['id'])) {
            $errors[] = 'Bundle id tidak boleh kosong';
        }

        if (($fhirBundleJson['type'] ?? '') !== 'transaction') {
            $errors[] = "Bundle type untuk sinkronisasi SatuSehat harus 'transaction'";
        }

        $entries = $fhirBundleJson['entry'] ?? [];
        if (! is_array($entries) || count($entries) === 0) {
            $errors[] = 'Bundle harus memiliki minimal 1 entry resource';
        } else {
            foreach ($entries as $idx => $entry) {
                if (empty($entry['fullUrl'])) {
                    $errors[] = "Entry #{$idx} kehilangan properti 'fullUrl'";
                }
                if (empty($entry['resource']['resourceType'])) {
                    $errors[] = "Entry #{$idx} kehilangan resourceType";
                }
            }
        }

        $isValid = count($errors) === 0;
        $score = $isValid ? 100 : max(0, 100 - (count($errors) * 20));

        return [
            'is_valid' => $isValid,
            'compliance_score_pct' => $score,
            'validation_errors' => $errors,
        ];
    }
}
