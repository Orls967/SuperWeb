<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Tenancy\TenantContext;

/**
 * Contract for owner modules exposing structured v2 resources through the Core API v2 gateway.
 */
interface ApiResourceProvider
{
    /**
     * URL slug for `/api/v2/{resource}` (e.g. 'purchase-orders').
     */
    public function resourceKey(): string;

    /**
     * Owner module name (e.g. 'Procurement').
     */
    public function moduleName(): string;

    /**
     * Human-readable resource title for OpenAPI & Developer Hub.
     */
    public function title(): string;

    /**
     * Optional legacy v1 path that triggers RFC 8594 Deprecation header on v2 responses.
     */
    public function deprecatedV1Path(): ?string;

    /**
     * Allowed filter column names and matching semantics ('exact' or 'like').
     *
     * @return array<string, string>
     */
    public function declaredFilters(): array;

    /**
     * Allowed sortable column names (excluding 'id' which is always tie-breaker).
     *
     * @return list<string>
     */
    public function declaredSorts(): array;

    /**
     * Default sort column name.
     */
    public function defaultSortColumn(): string;

    /**
     * Resource attribute schema definition for OpenAPI & Developer Hub.
     *
     * @return array<string, array{type: string, description: string}>
     */
    public function schemaProperties(): array;

    /**
     * Declared mutation actions callable via `/api/v2/{resource}/actions/{action}`.
     *
     * @return array<string, array{description: string, ability: string, required_fields: list<string>}>
     */
    public function declaredActions(): array;

    /**
     * Build owner query builder filtered only as allowed by tenant context when applicable.
     *
     * @return Builder<Model>
     */
    public function baseQuery(TenantContext $context): Builder;

    /**
     * Normalize an Eloquent model record into API v2 output representation (integer minor units, masked PII).
     *
     * @return array<string, mixed>
     */
    public function mapRecord(Model $record): array;

    /**
     * Execute a declared domain action idempotently.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, data: array<string, mixed>}
     */
    public function executeAction(string $action, array $payload, string $idempotencyKey, TenantContext $context): array;
}
