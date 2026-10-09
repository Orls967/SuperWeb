<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * GlobalSearchService (Fase 194)
 *
 * Implements:
 *  - 194.1 Scope-aware global search index across 30 lines (IDOR protection: strictly filters by tenant & role)
 *  - 194.3 Document full-text preview with automated PII masking
 */
class GlobalSearchService
{
    /**
     * Index entity with PII sanitization.
     */
    public function indexEntity(string $entityType, string $entityId, string $domainCode, string $tenantScope, string $allowedRole, string $text, string $rawPreview): object
    {
        // Sanitize PII (masks phone numbers and emails)
        $cleanPreview = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '[REDACTED_EMAIL]', $rawPreview);
        $cleanPreview = preg_replace('/(\+62|08)[0-9]{8,11}/', '[REDACTED_PHONE]', $cleanPreview);

        $id = DB::table('src_global_search_indices')->insertGetId([
            'entity_type' => strtoupper($entityType),
            'entity_id' => $entityId,
            'domain_code' => strtoupper($domainCode),
            'tenant_scope' => strtoupper($tenantScope),
            'allowed_role' => strtoupper($allowedRole),
            'searchable_text' => strtolower($text),
            'sanitized_preview' => $cleanPreview,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('src_global_search_indices')->find($id);
    }

    /**
     * Scope-aware search query. Anti-IDOR: strictly bounds results to requester tenant & role.
     */
    public function search(string $query, string $requesterTenant, string $requesterRole): array
    {
        $q = strtolower(trim($query));

        $results = DB::table('src_global_search_indices')
            ->where(function ($builder) use ($requesterTenant) {
                $builder->where('tenant_scope', 'PUBLIC')
                    ->orWhere('tenant_scope', strtoupper($requesterTenant));
            })
            ->where(function ($builder) use ($requesterRole) {
                $builder->where('allowed_role', 'ALL')
                    ->orWhere('allowed_role', strtoupper($requesterRole));
            })
            ->where('searchable_text', 'like', "%{$q}%")
            ->get();

        return $results->toArray();
    }

    /**
     * Quality audit gate (`search:audit`).
     */
    public function audit(): array
    {
        $unmaskedPii = DB::table('src_global_search_indices')
            ->where(function ($builder) {
                $builder->where('sanitized_preview', 'like', '%@%')
                    ->orWhere('sanitized_preview', 'like', '%+628%');
            })
            ->count();

        return [
            'status' => $unmaskedPii === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_indexed_entities' => DB::table('src_global_search_indices')->count(),
            'discrepancy_count' => $unmaskedPii,
        ];
    }
}
