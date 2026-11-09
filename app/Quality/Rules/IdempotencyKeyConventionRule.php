<?php

declare(strict_types=1);

namespace App\Quality\Rules;

/**
 * Aturan Kualitas: Memvalidasi konvensi format kunci idempotensi ({prefix}:{action}:{id}).
 */
final class IdempotencyKeyConventionRule
{
    /**
     * @return array{is_compliant: bool, error: ?string}
     */
    public function validateKeyFormat(string $idempotencyKey): array
    {
        // Format wajib: 3 bagian dipisah titik dua
        $parts = explode(':', $idempotencyKey);
        if (count($parts) < 3) {
            return [
                'is_compliant' => false,
                'error' => "Kunci idempotensi wajib mengikuti format '{prefix}:{action}:{id}', ditemukan: {$idempotencyKey}",
            ];
        }

        [$prefix, $action, $id] = $parts;

        if (strlen($prefix) < 2 || strlen($action) < 2 || strlen($id) < 1) {
            return [
                'is_compliant' => false,
                'error' => 'Segmen kunci idempotensi tidak boleh kosong atau terlalu pendek',
            ];
        }

        return [
            'is_compliant' => true,
            'error' => null,
        ];
    }
}

