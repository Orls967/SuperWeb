<?php

declare(strict_types=1);

namespace App\Quality\Rules;

/**
 * Aturan Kualitas: Memastikan representasi nominal uang tidak menggunakan tipe data float (X22).
 */
final class MoneyIntegerRule
{
    /**
     * @return array{is_compliant: bool, violations: list<string>}
     */
    public function scanPropertyTypes(string $source): array
    {
        $violations = [];

        if (preg_match_all('/public\s+float\s+\$([a-zA-Z0-9_]*(price|amount|balance|cost|total|fee)[a-zA-Z0-9_]*)/i', $source, $matches)) {
            foreach ($matches[1] as $prop) {
                $violations[] = "Properti uang \${$prop} dideklarasikan bertipe 'float' (wajib integer rupiah atau Money VO)";
            }
        }

        return [
            'is_compliant' => empty($violations),
            'violations' => $violations,
        ];
    }
}

