<?php

declare(strict_types=1);

namespace App\Quality\ArchScan\Rules;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;

/**
 * A8 — production code moves the global test clock (X14, K-09). It changes the
 * time for the whole process; simulation must use an injected ClockInterface.
 */
final class TestNowOutsideTestsRule implements Rule
{
    private const CLOCK_CLASSES = ['Carbon', 'CarbonImmutable', 'Date'];

    public function id(): string
    {
        return 'A8';
    }

    public function description(): string
    {
        return 'Carbon/Date::setTestNow di luar tests/';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if ($file->module() === null || $file->isTest()) {
            return [];
        }

        $tokens = $file->tokens();
        $violations = [];

        foreach ($tokens as $index => $token) {
            foreach (['setTestNow', 'setTestNowAndTimezone'] as $method) {
                if (! Tokens::isStaticCall($tokens, $index, self::CLOCK_CLASSES, $method)) {
                    continue;
                }

                $call = Tokens::shortName($token).'::'.$method;
                $violations[] = new Violation(
                    $this->id(),
                    $file->relativePath,
                    $token->line,
                    $call,
                    "{$call}() di kode produksi menggeser jam seluruh proses; pakai ClockInterface (KONSEP §A10.1).",
                );
            }
        }

        return $violations;
    }
}
