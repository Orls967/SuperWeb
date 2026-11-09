<?php

declare(strict_types=1);

namespace App\Quality\Analyzers;

/**
 * Pemindai Impor Tidak Terpakai (Unused Use Statements Scanner).
 */
final class UnusedImportScanner
{
    /**
     * @return list<string> Daftar namespace import yang tidak pernah dipanggil di body kode
     */
    public function scanUnusedImports(string $source): array
    {
        $tokens = @token_get_all($source);
        $count = count($tokens);

        $imports = [];
        $bodyTokens = [];
        $inImport = false;
        $currentImport = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                $id = $token[0];
                if ($id === T_USE && ! $inImport) {
                    $inImport = true;
                    $currentImport = [];
                } elseif ($inImport) {
                    if ($id === T_STRING || $id === T_NAME_QUALIFIED) {
                        $currentImport[] = $token[1];
                    }
                } else {
                    $bodyTokens[] = $token[1];
                }
            } else {
                if ($token === ';' && $inImport) {
                    $inImport = false;
                    if (! empty($currentImport)) {
                        $full = implode('\\', $currentImport);
                        $parts = explode('\\', $full);
                        $short = end($parts);
                        $imports[$short] = $full;
                    }
                } elseif (! $inImport) {
                    $bodyTokens[] = $token;
                }
            }
        }

        $bodyText = implode(' ', $bodyTokens);
        $unused = [];

        foreach ($imports as $shortName => $fullNamespace) {
            // Cek apakah short name dipanggil di body
            if (! preg_match('/\b' . preg_quote($shortName, '/') . '\b/', $bodyText)) {
                $unused[] = $fullNamespace;
            }
        }

        return $unused;
    }
}

