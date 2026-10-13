<?php

declare(strict_types=1);

namespace App\Quality\TestHygiene;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;

/**
 * T1 — an assertion that can never fail: `assertTrue(true)` or
 * `expect(true)->toBeTrue()` (X15, K-27). A test must assert an effect.
 */
final class TrivialAssertionRule implements Rule
{
    public function id(): string
    {
        return 'T1';
    }

    public function description(): string
    {
        return 'Assertion yang tidak mungkin gagal: assertTrue(true) / expect(true)->toBeTrue()';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if (! $file->isTest()) {
            return [];
        }

        $tokens = $file->tokens();
        $violations = [];

        foreach ($tokens as $index => $token) {
            $next = static fn (int $offset): string => strtolower($tokens[$index + $offset]->text ?? '');

            $assertTrue = $token->is(T_STRING) && strtolower($token->text) === 'asserttrue'
                && $next(1) === '(' && $next(2) === 'true' && in_array($next(3), [')', ','], true);
            $expectTrue = $token->is(T_STRING) && strtolower($token->text) === 'expect'
                && $next(1) === '(' && $next(2) === 'true' && $next(3) === ')'
                && $next(4) === '->' && $next(5) === 'tobetrue';

            if ($assertTrue || $expectTrue) {
                $signature = $assertTrue ? 'assertTrue(true)' : 'expect(true)->toBeTrue()';
                $violations[] = new Violation($this->id(), $file->relativePath, $token->line, $signature, "{$signature} tidak menguji apa pun; assert efek nyata atau pakai expectNotToPerformAssertions().");
            }
        }

        return $violations;
    }
}
