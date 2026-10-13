<?php

declare(strict_types=1);

namespace App\Quality\Ledger;

use App\Quality\Support\SourceFile;

/**
 * Scans production source code for ledger account references and verifies
 * they are registered and seeded in the chart of accounts (KONSEP.md §A14.5).
 */
final class LedgerAccountScanner
{
    /**
     * @param  iterable<SourceFile>  $files
     * @return list<string>
     */
    public static function referencedAccounts(iterable $files): array
    {
        $accounts = [];

        foreach ($files as $file) {
            if ($file->isTest()) {
                continue;
            }

            if (! str_contains($file->contents, 'forCode')
                && ! str_contains($file->contents, 'debit')
                && ! str_contains($file->contents, 'credit')) {
                continue;
            }

            if (preg_match_all('/(?:PostingEntryDTO::forCode|->debit|->credit)\(\s*([\"\x27][^\"\x27]+[\"\x27])/', $file->contents, $matches)) {
                foreach ($matches[1] as $raw) {
                    $code = trim($raw, "\"\x27");
                    // Normalize string interpolations like "{$var}" to wildcard "*"
                    $normalized = preg_replace('/\{[^}]+\}/', '*', $code);
                    $accounts[$normalized] = true;
                }
            }
        }

        $codes = array_keys($accounts);
        sort($codes);

        return $codes;
    }

    /**
     * Finds referenced accounts that are not present in the seeded accounts list.
     *
     * @param  list<string>  $referencedCodes
     * @param  list<string>  $seededCodes
     * @return list<string> List of "account:{code}" keys for set baseline
     */
    public static function unseededAccounts(array $referencedCodes, array $seededCodes): array
    {
        $seededSet = array_flip($seededCodes);
        $missing = [];

        foreach ($referencedCodes as $code) {
            if (isset($seededSet[$code])) {
                continue;
            }

            $matched = false;
            foreach ($seededCodes as $seeded) {
                if (fnmatch($code, $seeded) || fnmatch($seeded, $code)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                $missing[] = "account:{$code}";
            }
        }

        sort($missing);

        return $missing;
    }
}
