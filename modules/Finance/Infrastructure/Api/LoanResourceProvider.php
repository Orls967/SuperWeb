<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Api;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Contracts\ApiResourceProvider;
use Modules\Finance\Domain\Models\Loan;

class LoanResourceProvider implements ApiResourceProvider
{
    public function resourceKey(): string
    {
        return 'loans';
    }

    public function moduleName(): string
    {
        return 'Finance';
    }

    public function title(): string
    {
        return 'Finance Loans';
    }

    public function deprecatedV1Path(): ?string
    {
        return null;
    }

    public function declaredFilters(): array
    {
        return [
            'status' => 'exact',
            'user_id' => 'exact',
        ];
    }

    public function declaredSorts(): array
    {
        return ['created_at', 'principal', 'status'];
    }

    public function defaultSortColumn(): string
    {
        return 'created_at';
    }

    public function schemaProperties(): array
    {
        return [
            'id' => ['type' => 'integer', 'description' => 'Loan internal ID'],
            'uuid' => ['type' => 'string', 'description' => 'Loan UUID'],
            'status' => ['type' => 'string', 'description' => 'Loan status'],
            'principal' => ['type' => 'integer', 'description' => 'Original principal amount'],
            'outstanding_principal' => ['type' => 'integer', 'description' => 'Remaining outstanding balance'],
        ];
    }

    public function declaredActions(): array
    {
        return [];
    }

    public function baseQuery(TenantContext $context): Builder
    {
        return Loan::query();
    }

    public function mapRecord(Model $record): array
    {
        /** @var Loan $record */
        return [
            'id' => (int) $record->id,
            'uuid' => (string) $record->uuid,
            'status' => (string) ($record->status->value ?? $record->status),
            'principal' => (int) $record->principal,
            'outstanding_principal' => (int) $record->outstanding_principal,
            'created_at' => $record->created_at?->toIso8601String(),
        ];
    }

    public function executeAction(string $action, array $payload, string $idempotencyKey, TenantContext $context): array
    {
        throw new DomainException("ACTION_UNKNOWN: Aksi [{$action}] tidak didukung pada loans.");
    }
}
