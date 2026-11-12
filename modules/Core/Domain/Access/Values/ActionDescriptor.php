<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Values;

use InvalidArgumentException;

/**
 * Immutable descriptor representing the action attempted on a resource (W8-D53).
 *
 * Categorizes risk levels, determines whether dual-custody approval is needed,
 * and sets audit logging intensity.
 */
final class ActionDescriptor
{
    public const CATEGORY_READ = 'READ';

    public const CATEGORY_WRITE = 'WRITE';

    public const CATEGORY_DELETE = 'DELETE';

    public const CATEGORY_ADMIN = 'ADMIN';

    public const CATEGORY_EXPORT = 'EXPORT';

    public const CATEGORY_IMPERSONATE = 'IMPERSONATE';

    public const CATEGORY_BREAK_GLASS = 'BREAK_GLASS';

    public const RISK_LOW = 'LOW';

    public const RISK_MEDIUM = 'MEDIUM';

    public const RISK_HIGH = 'HIGH';

    public const RISK_CRITICAL = 'CRITICAL';

    /**
     * @param  string  $actionCode  Specific action identifier (e.g., 'financial.export_tranche', 'patient.view_history')
     * @param  string  $category  High-level category (READ, WRITE, DELETE, ADMIN, EXPORT, IMPERSONATE, BREAK_GLASS)
     * @param  string  $riskTier  Risk severity tier (LOW, MEDIUM, HIGH, CRITICAL)
     * @param  bool  $requiresDualCustody  Whether action requires two independent authorizations
     * @param  bool  $requiresStepUpMfa  Whether action demands step-up MFA verification
     * @param  array<string, mixed>  $actionParameters  Parameters attached to the action request
     */
    public function __construct(
        public readonly string $actionCode,
        public readonly string $category = self::CATEGORY_READ,
        public readonly string $riskTier = self::RISK_LOW,
        public readonly bool $requiresDualCustody = false,
        public readonly bool $requiresStepUpMfa = false,
        public readonly array $actionParameters = []
    ) {
        $validCategories = [
            self::CATEGORY_READ,
            self::CATEGORY_WRITE,
            self::CATEGORY_DELETE,
            self::CATEGORY_ADMIN,
            self::CATEGORY_EXPORT,
            self::CATEGORY_IMPERSONATE,
            self::CATEGORY_BREAK_GLASS,
        ];

        if (! in_array($category, $validCategories, true)) {
            throw new InvalidArgumentException("Invalid action category: {$category}");
        }

        $validRisks = [self::RISK_LOW, self::RISK_MEDIUM, self::RISK_HIGH, self::RISK_CRITICAL];
        if (! in_array($riskTier, $validRisks, true)) {
            throw new InvalidArgumentException("Invalid risk tier: {$riskTier}");
        }
    }

    /**
     * Check if action is high risk or critical.
     */
    public function isHighOrCriticalRisk(): bool
    {
        return $this->riskTier === self::RISK_HIGH || $this->riskTier === self::RISK_CRITICAL;
    }

    /**
     * Export as flat evaluation dictionary for condition matching.
     *
     * @return array<string, mixed>
     */
    public function toEvaluationArray(): array
    {
        return array_merge($this->actionParameters, [
            'action_code' => $this->actionCode,
            'category' => $this->category,
            'risk_tier' => $this->riskTier,
            'requires_dual_custody' => $this->requiresDualCustody,
            'requires_step_up_mfa' => $this->requiresStepUpMfa,
        ]);
    }
}
