<?php

declare(strict_types=1);

namespace App\Quality\TestHygiene;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;

/**
 * T4 — a module defines routes but none of its tests sends an HTTP request
 * (K-06, K-27): routes, middleware and controllers are then never exercised.
 */
final class ModuleWithoutHttpTestRule implements Rule
{
    /**
     * @param  list<string>  $modulesWithHttpTests
     */
    public function __construct(private readonly array $modulesWithHttpTests) {}

    public function id(): string
    {
        return 'T4';
    }

    public function description(): string
    {
        return 'Modul punya rute tetapi tidak satu pun test-nya melakukan request HTTP';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        $module = $file->module();

        if ($module === null || preg_match('#(?:^|/)modules/[^/]+/routes/[^/]+\.php$#', $file->relativePath) !== 1) {
            return [];
        }

        if (in_array($module, $this->modulesWithHttpTests, true) || ! $this->definesRoutes($file)) {
            return [];
        }

        return [new Violation(
            $this->id(),
            $file->relativePath,
            1,
            "modul {$module} tanpa test HTTP",
            "Rute modul {$module} tidak pernah diuji lewat HTTP (role berhak & tak berhak).",
        )];
    }

    private function definesRoutes(SourceFile $file): bool
    {
        $tokens = $file->tokens();

        foreach ($tokens as $index => $token) {
            if (Tokens::isNameToken($token) && Tokens::shortName($token) === 'Route' && ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON)) {
                return true;
            }
        }

        return false;
    }
}
