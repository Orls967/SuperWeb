<?php

declare(strict_types=1);

namespace App\Quality\ArchScan\Rules;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;

/**
 * A11 — a module PHP file without `declare(strict_types=1)` (K-25). Migrations are exempt.
 */
final class MissingStrictTypesRule implements Rule
{
    public function id(): string
    {
        return 'A11';
    }

    public function description(): string
    {
        return 'File PHP modul tanpa declare(strict_types=1) (migrasi dikecualikan)';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if ($file->module() === null || $file->isMigration()) {
            return [];
        }

        $tokens = array_slice($file->tokens(), 0, 8);

        if ($tokens === [] || $this->declaresStrictTypes($tokens)) {
            return [];
        }

        return [new Violation(
            $this->id(),
            $file->relativePath,
            1,
            'declare(strict_types=1) tidak ada',
            'Tambahkan declare(strict_types=1) di baris pertama setelah <?php.',
        )];
    }

    /**
     * @param  list<\PhpToken>  $tokens
     */
    private function declaresStrictTypes(array $tokens): bool
    {
        return isset($tokens[4])
            && $tokens[0]->is(T_DECLARE)
            && $tokens[1]->text === '('
            && strtolower($tokens[2]->text) === 'strict_types'
            && $tokens[3]->text === '='
            && $tokens[4]->text === '1';
    }
}
