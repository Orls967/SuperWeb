<?php

declare(strict_types=1);

namespace Modules\Treasury\Infrastructure\Api;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Contracts\ApiResourceProvider;
use Modules\Treasury\Domain\Models\ExchangeRate;

class ExchangeRateResourceProvider implements ApiResourceProvider
{
    public function resourceKey(): string
    {
        return 'exchange-rates';
    }

    public function moduleName(): string
    {
        return 'Treasury';
    }

    public function title(): string
    {
        return 'Exchange Rates';
    }

    public function deprecatedV1Path(): ?string
    {
        return null;
    }

    public function declaredFilters(): array
    {
        return [
            'from_currency' => 'exact',
            'to_currency' => 'exact',
            'rate_type' => 'exact',
        ];
    }

    public function declaredSorts(): array
    {
        return ['rate_date', 'from_currency', 'to_currency'];
    }

    public function defaultSortColumn(): string
    {
        return 'rate_date';
    }

    public function schemaProperties(): array
    {
        return [
            'id' => ['type' => 'string', 'description' => 'Exchange rate UUID'],
            'from_currency' => ['type' => 'string', 'description' => 'Source ISO 4217 currency code'],
            'to_currency' => ['type' => 'string', 'description' => 'Target ISO 4217 currency code'],
            'rate_numerator' => ['type' => 'integer', 'description' => 'Integer rate numerator'],
            'rate_denominator' => ['type' => 'integer', 'description' => 'Integer rate denominator'],
            'rate_date' => ['type' => 'string', 'description' => 'Effective rate date (YYYY-MM-DD)'],
        ];
    }

    public function declaredActions(): array
    {
        return [];
    }

    public function baseQuery(TenantContext $context): Builder
    {
        return ExchangeRate::query();
    }

    public function mapRecord(Model $record): array
    {
        /** @var ExchangeRate $record */
        return [
            'id' => (string) $record->id,
            'from_currency' => (string) $record->from_currency,
            'to_currency' => (string) $record->to_currency,
            'rate_numerator' => (int) $record->rate_numerator,
            'rate_denominator' => (int) $record->rate_denominator,
            'rate_date' => $record->rate_date?->format('Y-m-d'),
        ];
    }

    public function executeAction(string $action, array $payload, string $idempotencyKey, TenantContext $context): array
    {
        throw new DomainException("ACTION_UNKNOWN: Aksi [{$action}] tidak didukung pada exchange-rates.");
    }
}

