<?php

declare(strict_types=1);

namespace App\Quality\ArchScan\Rules;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\Declarations;
use App\Quality\Support\SourceFile;

/**
 * A6 — an application service/action trusts a boolean "control" passed by the
 * caller (`$approved`, `$kycVerified`, `$consentGiven`, …) instead of deriving it
 * from stored approval records, evidence or checks (X7, K-21). Parameters that
 * only filter a view can be exempted with #[NotAControl('reason')].
 */
final class BooleanControlParameterRule implements Rule
{
    private const CONTROL_WORDS = ['approved', 'verified', 'passed', 'compliant', 'signed', 'consent', 'consents', 'consented', 'evidence', 'attested', 'certified', 'granted'];

    public function id(): string
    {
        return 'A6';
    }

    public function description(): string
    {
        return 'Parameter bool kontrol ($approved, $verified*, $consent*, …) di Application/';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if (! $file->isInModuleDirectory('Application')) {
            return [];
        }

        $violations = [];

        foreach (Declarations::parameters($file) as $parameter) {
            if (! in_array('bool', $parameter['types'], true) || self::isExempted($parameter['attributes'])) {
                continue;
            }

            if (! self::isControlName($parameter['name'])) {
                continue;
            }

            $violations[] = new Violation(
                $this->id(),
                $file->relativePath,
                $parameter['line'],
                "bool \${$parameter['name']} @ {$parameter['function']}()",
                "Kontrol \${$parameter['name']} dipercaya dari pemanggil; turunkan dari ApprovalEngine/bukti tersimpan (KONSEP §A3).",
            );
        }

        return $violations;
    }

    /**
     * Exempt only with a written reason of at least 10 characters: #[NotAControl('…')].
     */
    private static function isExempted(string $attributes): bool
    {
        return preg_match('/NotAControl\(\s*(reason\s*:\s*)?([\'"])[^\'"]{10,}\2/', $attributes) === 1;
    }

    public static function isControlName(string $name): bool
    {
        $snake = strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $name));

        return array_intersect(explode('_', $snake), self::CONTROL_WORDS) !== [];
    }
}
