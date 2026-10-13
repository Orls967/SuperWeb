<?php

declare(strict_types=1);

namespace App\Quality\ArchScan\Rules;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;

/**
 * A13 — `back()->errors()` does not exist on RedirectResponse, so the error path
 * throws and the user gets HTTP 500 instead of a validation message (X15, K-29).
 */
final class BackErrorsRule implements Rule
{
    public function id(): string
    {
        return 'A13';
    }

    public function description(): string
    {
        return 'back()->errors() (HTTP 500 di jalur galat)';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if ($file->module() === null) {
            return [];
        }

        $tokens = $file->tokens();
        $violations = [];

        foreach ($tokens as $index => $token) {
            $matches = $token->is(T_STRING) && strtolower($token->text) === 'back'
                && ($tokens[$index + 1]->text ?? '') === '('
                && ($tokens[$index + 2]->text ?? '') === ')'
                && ($tokens[$index + 3] ?? null)?->is(T_OBJECT_OPERATOR)
                && strtolower($tokens[$index + 4]->text ?? '') === 'errors'
                && ($tokens[$index + 5]->text ?? '') === '(';

            if ($matches) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->relativePath,
                    $token->line,
                    'back()->errors()',
                    'RedirectResponse tidak punya errors(); pakai back()->withErrors([...])->withInput() (KONSEP §A15.4).',
                );
            }
        }

        return $violations;
    }
}
