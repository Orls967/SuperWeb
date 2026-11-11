<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Deterministic longest-prefix table ownership resolver (R4-ALG-02, W8-D42).
 * Supports nested hierarchical prefixes, conflict detection (PREFIX_CONFLICT),
 * explicit UNOWNED resolution, and shared table waivers.
 *
 * Complexity: O(P log P) initialization, O(P) per table lookup (P <= 500 prefixes). Integer/string ops only.
 */
final class TablePrefixOwnershipResolver
{
    /** @var array<string, string> prefix => owner, sorted by length descending then alphabetically */
    private array $sortedPrefixes;

    /**
     * @param  array<string, string>  $tablePrefixes  prefix => owner
     * @param  array<string, string>  $legacyTables  table => owner
     * @param  array<string, list<string>>  $sharedTables  module => list of tables
     */
    public function __construct(
        array $tablePrefixes,
        private readonly array $legacyTables = [],
        private readonly array $sharedTables = [],
    ) {
        uksort($tablePrefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));
        $this->sortedPrefixes = $tablePrefixes;
    }

    /**
     * Resolve table ownership and check authorization for the requesting module.
     */
    public function resolve(string $tableName, ?string $requestingModule = null): TableResolutionResult
    {
        $cleanTable = preg_split('/\s+(?:as\s+)?/i', trim($tableName))[0];

        // 1. Check legacy tables (exact match)
        if (isset($this->legacyTables[$cleanTable])) {
            $owner = $this->legacyTables[$cleanTable];
            $isAllowed = $this->isModuleAllowed($cleanTable, $owner, $requestingModule);

            return new TableResolutionResult(
                owner: $owner,
                isAllowed: $isAllowed,
                matchedPrefix: null,
                isLegacy: true,
                reason: $isAllowed ? 'Authorized legacy table' : "Table '{$cleanTable}' is a legacy table owned by {$owner}",
            );
        }

        // 2. Longest prefix matching
        $owner = null;
        $matchedPrefix = null;

        foreach ($this->sortedPrefixes as $prefix => $moduleOwner) {
            if (str_starts_with($cleanTable, $prefix)) {
                $owner = $moduleOwner;
                $matchedPrefix = $prefix;
                break;
            }
        }

        if ($owner === null) {
            return new TableResolutionResult(
                owner: null,
                isAllowed: false,
                matchedPrefix: null,
                isLegacy: false,
                reason: "Prefix for table '{$cleanTable}' is not registered in config/modules.php",
            );
        }

        $isAllowed = $this->isModuleAllowed($cleanTable, $owner, $requestingModule);
        $reason = $isAllowed
            ? 'Authorized module owner or shared table access'
            : "Module {$requestingModule} is not authorized to access table '{$cleanTable}' owned by {$owner}";

        return new TableResolutionResult(
            owner: $owner,
            isAllowed: $isAllowed,
            matchedPrefix: $matchedPrefix,
            isLegacy: false,
            reason: $reason,
        );
    }

    /**
     * Return registered prefixes.
     *
     * @return array<string, string>
     */
    public function prefixes(): array
    {
        return $this->sortedPrefixes;
    }

    /**
     * Resolve owner module name or return 'UNOWNED' when no prefix matches.
     */
    public function resolveOwnershipLabel(string $tableName): string
    {
        return $this->resolve($tableName)->owner ?? 'UNOWNED';
    }

    /**
     * Detect identical prefixes claimed by multiple distinct modules (PREFIX_CONFLICT).
     *
     * @param  list<array{prefix: string, owner: string}>  $registrations
     * @return list<string> Sorted conflicting prefixes
     */
    public static function detectConflicts(array $registrations): array
    {
        $ownersByPrefix = [];
        foreach ($registrations as $entry) {
            $prefix = (string) ($entry['prefix'] ?? '');
            $owner = (string) ($entry['owner'] ?? '');
            if ($prefix === '') {
                continue;
            }
            $ownersByPrefix[$prefix][$owner] = true;
        }

        $conflicts = [];
        foreach ($ownersByPrefix as $prefix => $owners) {
            if (count($owners) > 1) {
                $conflicts[] = $prefix;
            }
        }

        sort($conflicts, SORT_STRING);

        return $conflicts;
    }

    private function isModuleAllowed(string $table, string $owner, ?string $requestingModule): bool
    {
        if ($requestingModule === null) {
            return true; // No client module specified
        }

        if ($requestingModule === $owner) {
            return true;
        }

        if (isset($this->sharedTables[$requestingModule]) && in_array($table, $this->sharedTables[$requestingModule], true)) {
            return true;
        }

        if (isset($this->sharedTables['*']) && in_array($table, $this->sharedTables['*'], true)) {
            return true;
        }

        return false;
    }
}
