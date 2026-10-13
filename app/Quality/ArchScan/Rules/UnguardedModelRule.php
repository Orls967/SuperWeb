<?php

declare(strict_types=1);

namespace App\Quality\ArchScan\Rules;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;

/**
 * A10 — `$guarded = []` makes every column mass-assignable (K-25); declare `$fillable`.
 */
final class UnguardedModelRule implements Rule
{
    public function id(): string
    {
        return 'A10';
    }

    public function description(): string
    {
        return 'Model dengan $guarded = [] (semua kolom mass-assignable)';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if ($file->module() === null) {
            return [];
        }

        $tokens = $file->tokens();
        $violations = [];

        foreach ($tokens as $index => $token) {
            if (! $token->is(T_VARIABLE) || $token->text !== '$guarded' || ($tokens[$index + 1]->text ?? '') !== '=') {
                continue;
            }

            $isEmptyArray = (($tokens[$index + 2]->text ?? '') === '[' && ($tokens[$index + 3]->text ?? '') === ']')
                || (($tokens[$index + 2] ?? null)?->is(T_ARRAY) && ($tokens[$index + 4]->text ?? '') === ')');

            if ($isEmptyArray) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->relativePath,
                    $token->line,
                    '$guarded = []',
                    'Model tanpa proteksi mass assignment; ganti dengan $fillable eksplisit (V2).',
                );
            }
        }

        return $violations;
    }
}
