<?php

declare(strict_types=1);

namespace App\Quality\ArchScan;

use App\Quality\ArchScan\Rules\BackErrorsRule;
use App\Quality\ArchScan\Rules\BooleanControlParameterRule;
use App\Quality\ArchScan\Rules\CrossModuleDomainImportRule;
use App\Quality\ArchScan\Rules\FloatMoneyRule;
use App\Quality\ArchScan\Rules\ForeignTablePrefixRule;
use App\Quality\ArchScan\Rules\KernelDependsOnBusinessModuleRule;
use App\Quality\ArchScan\Rules\LedgerTransactionTypeRule;
use App\Quality\ArchScan\Rules\MissingForeignKeyRule;
use App\Quality\ArchScan\Rules\MissingStrictTypesRule;
use App\Quality\ArchScan\Rules\NonDeterministicIdempotencyKeyRule;
use App\Quality\ArchScan\Rules\TestNowOutsideTestsRule;
use App\Quality\ArchScan\Rules\UnguardedModelRule;
use App\Quality\ArchScan\Rules\UnkeyedHashOfSensitiveValueRule;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;
use App\Quality\Support\SourceFinder;

/**
 * Runs the `arch:scan` rules (A1–A13, KONSEP.md §A14.1) over source files.
 */
final class ArchScanner
{
    /**
     * @param  list<Rule>  $rules
     */
    public function __construct(
        private readonly array $rules,
        private readonly ModuleRegistry $registry,
    ) {}

    /**
     * @return list<Rule>
     */
    public static function defaultRules(): array
    {
        return [
            new CrossModuleDomainImportRule,
            new ForeignTablePrefixRule,
            new FloatMoneyRule,
            new NonDeterministicIdempotencyKeyRule,
            new LedgerTransactionTypeRule,
            new BooleanControlParameterRule,
            new UnkeyedHashOfSensitiveValueRule,
            new TestNowOutsideTestsRule,
            new KernelDependsOnBusinessModuleRule,
            new UnguardedModelRule,
            new MissingStrictTypesRule,
            new MissingForeignKeyRule,
            new BackErrorsRule,
        ];
    }

    /**
     * Scan module sources the way `arch:scan` and the gate do: all PHP files under
     * $paths except module test directories, with the default rules.
     *
     * @param  array<string, mixed>  $moduleConfig  contents of config/modules.php
     * @param  list<string>  $paths  directories relative to $root
     */
    public static function scanProject(string $root, array $moduleConfig, array $paths = ['modules']): ScanReport
    {
        $files = SourceFinder::phpFiles($root, $paths, ['#(?:^|/)modules/[^/]+/tests/#']);

        return (new self(self::defaultRules(), ModuleRegistry::fromConfig($moduleConfig)))->scan($files);
    }

    /**
     * @param  iterable<SourceFile>  $files
     */
    public function scan(iterable $files): ScanReport
    {
        $descriptions = [];
        $violations = [];

        foreach ($this->rules as $rule) {
            $descriptions[$rule->id()] = $rule->description();
            $violations[$rule->id()] = [];
        }

        foreach ($files as $file) {
            foreach ($this->rules as $rule) {
                foreach ($rule->check($file, $this->registry) as $violation) {
                    $violations[$rule->id()][] = $violation;
                }
            }
        }

        foreach ($violations as $ruleId => $ruleViolations) {
            usort($ruleViolations, static fn (Violation $a, Violation $b): int => [$a->path, $a->line, $a->signature] <=> [$b->path, $b->line, $b->signature]);
            $violations[$ruleId] = $ruleViolations;
        }

        return new ScanReport($descriptions, $violations);
    }
}
