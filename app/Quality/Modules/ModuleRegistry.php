<?php

declare(strict_types=1);

namespace App\Quality\Modules;

/**
 * Ownership registry of modules and table prefixes (KONSEP.md §A1), read from config/modules.php.
 */
final class ModuleRegistry
{
    /** @var array<string, string> prefix => owner, longest prefix first */
    private array $tablePrefixes;

    /**
     * @param  array<string, string>  $tablePrefixes  prefix including the trailing underscore => owner module
     * @param  array<string, string>  $legacyTables  exact table name => owner module
     * @param  list<string>  $kernelModules  modules that must not depend on business modules (A9)
     * @param  list<string>  $sharedKernelNamespaces  namespaces every module may import (A1 exceptions)
     * @param  array<string, string>  $crossModuleColumns  `table.column` => reason (A12 exceptions)
     */
    public function __construct(
        array $tablePrefixes,
        private readonly array $legacyTables = [],
        private readonly array $kernelModules = [],
        private readonly array $sharedKernelNamespaces = [],
        private readonly array $crossModuleColumns = [],
    ) {
        uksort($tablePrefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));
        $this->tablePrefixes = $tablePrefixes;
    }

    /**
     * @param  array{table_prefixes?: array<string, string>, legacy_tables?: array<string, string>, kernel_modules?: list<string>, shared_kernel_namespaces?: list<string>, cross_module_columns?: array<string, string>}  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            $config['table_prefixes'] ?? [],
            $config['legacy_tables'] ?? [],
            $config['kernel_modules'] ?? [],
            $config['shared_kernel_namespaces'] ?? [],
            $config['cross_module_columns'] ?? [],
        );
    }

    /**
     * Owner module of a table, or null when neither the exact name nor its prefix is registered.
     */
    public function ownerOfTable(string $table): ?string
    {
        if (isset($this->legacyTables[$table])) {
            return $this->legacyTables[$table];
        }

        foreach ($this->tablePrefixes as $prefix => $owner) {
            if (str_starts_with($table, $prefix)) {
                return $owner;
            }
        }

        return null;
    }

    public function isKernelModule(string $module): bool
    {
        return in_array($module, $this->kernelModules, true);
    }

    public function isSharedKernelNamespace(string $fullyQualifiedName): bool
    {
        foreach ($this->sharedKernelNamespaces as $namespace) {
            if (str_starts_with($fullyQualifiedName, rtrim($namespace, '\\').'\\')) {
                return true;
            }
        }

        return false;
    }

    public function isCrossModuleColumn(string $table, string $column): bool
    {
        return isset($this->crossModuleColumns["{$table}.{$column}"]);
    }
}
