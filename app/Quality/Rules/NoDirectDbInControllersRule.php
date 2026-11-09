<?php

declare(strict_types=1);

namespace App\Quality\Rules;

/**
 * Aturan Kualitas: Melarang pemanggilan langsung DB::table() di Controller (wajib lewat Action/Query).
 */
final class NoDirectDbInControllersRule
{
    /**
     * @return array{is_compliant: bool, violations: list<int>}
     */
    public function scan(string $source): array
    {
        $tokens = @token_get_all($source);
        $count = count($tokens);
        $violations = [];

        for ($i = 0; $i < $count - 2; $i++) {
            $t1 = $tokens[$i];
            $t2 = $tokens[$i + 1] ?? null;
            $t3 = $tokens[$i + 2] ?? null;

            if (is_array($t1) && is_array($t3)) {
                if ($t1[0] === T_STRING && $t1[1] === 'DB' && $t2 === '::') {
                    if ($t3[0] === T_STRING && in_array($t3[1], ['table', 'insert', 'update', 'delete', 'statement'], true)) {
                        $violations[] = $t1[2]; // Nomor baris
                    }
                }
            }
        }

        return [
            'is_compliant' => empty($violations),
            'violations' => $violations,
        ];
    }
}
