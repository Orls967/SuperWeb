<?php

declare(strict_types=1);

namespace Modules\Core\Application\Tenancy;

use Modules\Core\Contracts\TenantContextMissingException;
use Modules\Core\Domain\Models\CoreTenant;

/**
 * TenantContext holds active tenancy boundary state for the request/process.
 * Supports nesting via runWith() stack (ADR-0051).
 */
final class TenantContext
{
    /** @var array<int, self> */
    private static array $stack = [];

    /**
     * @param  array<int, string>  $allowedTenantIds
     * @param  array<int, int>  $allowedNumericTenantIds
     * @param  array<int, int>  $allowedEntityIds
     * @param  array<int, int>  $allowedPartyIds
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly array $allowedTenantIds = [],
        public readonly array $allowedNumericTenantIds = [],
        public readonly array $allowedEntityIds = [],
        public readonly array $allowedPartyIds = [],
    ) {}

    public static function current(): ?self
    {
        if (empty(self::$stack)) {
            return null;
        }

        return self::$stack[array_key_last(self::$stack)];
    }

    public static function requireCurrent(): self
    {
        $current = self::current();
        if ($current === null) {
            throw new TenantContextMissingException('Tenant context is required but none was bound in the current execution scope.');
        }

        return $current;
    }

    public static function push(self $context): void
    {
        self::$stack[] = $context;
    }

    public static function pop(): ?self
    {
        return array_pop(self::$stack);
    }

    public static function clear(): void
    {
        self::$stack = [];
    }

    /**
     * Execute callable within the provided tenant context.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function runWith(self|CoreTenant $context, callable $callback): mixed
    {
        $instance = $context instanceof self
            ? $context
            : new self(tenantId: (string) $context->id, allowedTenantIds: [(string) $context->id]);

        self::push($instance);
        try {
            return $callback();
        } finally {
            self::pop();
        }
    }
}
