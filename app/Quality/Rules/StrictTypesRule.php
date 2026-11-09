<?php

declare(strict_types=1);

namespace App\Quality\Rules;

/**
 * Aturan Kualitas: Memastikan deklarasi declare(strict_types=1) pada awal setiap file PHP.
 */
final class StrictTypesRule
{
    /**
     * @return array{is_compliant: bool, error: ?string}
     */
    public function validate(string $source): array
    {
        $tokens = @token_get_all($source);
        $foundDeclare = false;
        $foundStrict = false;

        $count = count($tokens);
        for ($i = 0; $i < min(20, $count); $i++) {
            $token = $tokens[$i];
            if (is_array($token)) {
                if ($token[0] === T_DECLARE) {
                    $foundDeclare = true;
                }
                if ($token[0] === T_STRING && $token[1] === 'strict_types') {
                    $foundStrict = true;
                }
            }
        }

        if (! $foundDeclare || ! $foundStrict) {
            return [
                'is_compliant' => false,
                'error' => 'Deklarasi declare(strict_types=1) wajib ada di awal file',
            ];
        }

        return [
            'is_compliant' => true,
            'error' => null,
        ];
    }
}

