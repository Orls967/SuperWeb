<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Values;

use InvalidArgumentException;

/**
 * Immutable descriptor representing the target resource in an ABAC evaluation (W8-D53, W8-D54).
 *
 * Captures data classification, tenant ownership, security boundaries,
 * and associated regulatory constraints.
 */
final class ResourceDescriptor
{
    public const CLASS_PUBLIC = 'PUBLIC';

    public const CLASS_INTERNAL = 'INTERNAL';

    public const CLASS_CONFIDENTIAL = 'CONFIDENTIAL';

    public const CLASS_RESTRICTED = 'RESTRICTED';

    public const CLASS_HIGHLY_SENSITIVE = 'HIGHLY_SENSITIVE';

    /**
     * @param  string  $resourceType  Machine type (e.g., 'financial_report', 'patient_record', 'api_key')
     * @param  string  $resourceId  Entity primary key or identifier
     * @param  string  $ownerTenantId  Tenant that owns the resource (primary boundary)
     * @param  string  $classification  Sensitivity level (PUBLIC, INTERNAL, CONFIDENTIAL, RESTRICTED, HIGHLY_SENSITIVE)
     * @param  string|null  $ownerDepartmentId  Department code that owns the resource
     * @param  int  $requiredClearance  Minimum clearance required to access (1 to 5)
     * @param  bool  $containsPii  Whether resource contains personally identifiable information
     * @param  bool  $isFinancialTranche  Whether resource is associated with high-value financial movements (> 500M)
     * @param  array<string, mixed>  $resourceAttributes  Dynamic resource attributes for fine-grained policy rules
     */
    public function __construct(
        public readonly string $resourceType,
        public readonly string $resourceId,
        public readonly string $ownerTenantId,
        public readonly string $classification = self::CLASS_INTERNAL,
        public readonly ?string $ownerDepartmentId = null,
        public readonly int $requiredClearance = 1,
        public readonly bool $containsPii = false,
        public readonly bool $isFinancialTranche = false,
        public readonly array $resourceAttributes = []
    ) {
        $allowedClassifications = [
            self::CLASS_PUBLIC,
            self::CLASS_INTERNAL,
            self::CLASS_CONFIDENTIAL,
            self::CLASS_RESTRICTED,
            self::CLASS_HIGHLY_SENSITIVE,
        ];

        if (! in_array($classification, $allowedClassifications, true)) {
            throw new InvalidArgumentException("Invalid classification: {$classification}");
        }

        if ($requiredClearance < 1 || $requiredClearance > 5) {
            throw new InvalidArgumentException("Required clearance must be 1-5, got: {$requiredClearance}");
        }
    }

    /**
     * Check if resource belongs to the provided tenant.
     */
    public function belongsToTenant(string $tenantId): bool
    {
        return $this->ownerTenantId === $tenantId;
    }

    /**
     * Check if resource requires elevated confidential handling.
     */
    public function isConfidentialOrAbove(): bool
    {
        return in_array($this->classification, [
            self::CLASS_CONFIDENTIAL,
            self::CLASS_RESTRICTED,
            self::CLASS_HIGHLY_SENSITIVE,
        ], true);
    }

    /**
     * Retrieve dynamic attribute or default.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->resourceAttributes[$key] ?? $default;
    }

    /**
     * Export as flat evaluation dictionary for condition matching.
     *
     * @return array<string, mixed>
     */
    public function toEvaluationArray(): array
    {
        return array_merge($this->resourceAttributes, [
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'owner_tenant_id' => $this->ownerTenantId,
            'classification' => $this->classification,
            'owner_department_id' => $this->ownerDepartmentId,
            'required_clearance' => $this->requiredClearance,
            'contains_pii' => $this->containsPii,
            'is_financial_tranche' => $this->isFinancialTranche,
        ]);
    }
}
