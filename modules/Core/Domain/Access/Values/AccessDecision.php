<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Values;

/**
 * Immutable value object representing the outcome of an ABAC evaluation (W8-D53).
 *
 * Implements the Deny-Overrides resolution algorithm result contract:
 * - If any matching policy evaluates to DENY, the final decision MUST be DENY.
 * - PERMIT is granted only if at least one policy evaluates to PERMIT and ZERO policies evaluate to DENY.
 * - If no policies match, the outcome is NOT_APPLICABLE (defaulting to safe deny).
 */
final class AccessDecision
{
    public const EFFECT_PERMIT = 'PERMIT';

    public const EFFECT_DENY = 'DENY';

    public const EFFECT_NOT_APPLICABLE = 'NOT_APPLICABLE';

    public const EFFECT_INDETERMINATE = 'INDETERMINATE';

    /**
     * @param  string  $effect  Final effect (PERMIT, DENY, NOT_APPLICABLE, INDETERMINATE)
     * @param  string  $decisionReason  Human-readable justification of the evaluation
     * @param  list<string>  $matchedPermitPolicies  Policy codes that evaluated to PERMIT
     * @param  list<string>  $matchedDenyPolicies  Policy codes that evaluated to DENY
     * @param  array<string, mixed>  $evaluationTrace  Step-by-step condition evaluation trace
     * @param  float  $executionTimeMicroseconds  Microseconds spent in policy evaluation
     * @param  string  $decisionHash  SHA-256 fingerprint of the decision parameters for auditability
     */
    public function __construct(
        public readonly string $effect,
        public readonly string $decisionReason,
        public readonly array $matchedPermitPolicies = [],
        public readonly array $matchedDenyPolicies = [],
        public readonly array $evaluationTrace = [],
        public readonly float $executionTimeMicroseconds = 0.0,
        public readonly string $decisionHash = ''
    ) {}

    /**
     * Create a PERMIT access decision.
     *
     * @param  list<string>  $matchedPermitPolicies
     * @param  array<string, mixed>  $trace
     */
    public static function permit(
        string $reason,
        array $matchedPermitPolicies,
        array $trace = [],
        float $executionTime = 0.0
    ): self {
        $hash = hash('sha256', json_encode([
            'effect' => self::EFFECT_PERMIT,
            'reason' => $reason,
            'permits' => $matchedPermitPolicies,
            'trace' => $trace,
        ], JSON_THROW_ON_ERROR));

        return new self(
            effect: self::EFFECT_PERMIT,
            decisionReason: $reason,
            matchedPermitPolicies: $matchedPermitPolicies,
            matchedDenyPolicies: [],
            evaluationTrace: $trace,
            executionTimeMicroseconds: $executionTime,
            decisionHash: $hash
        );
    }

    /**
     * Create a DENY access decision.
     *
     * @param  list<string>  $matchedDenyPolicies
     * @param  list<string>  $matchedPermitPolicies
     * @param  array<string, mixed>  $trace
     */
    public static function deny(
        string $reason,
        array $matchedDenyPolicies = [],
        array $matchedPermitPolicies = [],
        array $trace = [],
        float $executionTime = 0.0
    ): self {
        $hash = hash('sha256', json_encode([
            'effect' => self::EFFECT_DENY,
            'reason' => $reason,
            'denies' => $matchedDenyPolicies,
            'permits' => $matchedPermitPolicies,
            'trace' => $trace,
        ], JSON_THROW_ON_ERROR));

        return new self(
            effect: self::EFFECT_DENY,
            decisionReason: $reason,
            matchedPermitPolicies: $matchedPermitPolicies,
            matchedDenyPolicies: $matchedDenyPolicies,
            evaluationTrace: $trace,
            executionTimeMicroseconds: $executionTime,
            decisionHash: $hash
        );
    }

    /**
     * Create a NOT_APPLICABLE decision when no target policies matched.
     *
     * @param  array<string, mixed>  $trace
     */
    public static function notApplicable(string $reason, array $trace = [], float $executionTime = 0.0): self
    {
        $hash = hash('sha256', json_encode([
            'effect' => self::EFFECT_NOT_APPLICABLE,
            'reason' => $reason,
            'trace' => $trace,
        ], JSON_THROW_ON_ERROR));

        return new self(
            effect: self::EFFECT_NOT_APPLICABLE,
            decisionReason: $reason,
            matchedPermitPolicies: [],
            matchedDenyPolicies: [],
            evaluationTrace: $trace,
            executionTimeMicroseconds: $executionTime,
            decisionHash: $hash
        );
    }

    /**
     * Check if authorization was granted.
     */
    public function isAllowed(): bool
    {
        return $this->effect === self::EFFECT_PERMIT;
    }

    /**
     * Check if authorization was explicitly or default denied.
     */
    public function isDenied(): bool
    {
        return $this->effect !== self::EFFECT_PERMIT;
    }

    /**
     * Check if decision was caused by explicit Deny-Overrides.
     */
    public function wasOverriddenByDeny(): bool
    {
        return count($this->matchedDenyPolicies) > 0 && count($this->matchedPermitPolicies) > 0;
    }

    /**
     * Convert to an array representation for API serialization or logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'effect' => $this->effect,
            'is_allowed' => $this->isAllowed(),
            'decision_reason' => $this->decisionReason,
            'matched_permit_policies' => $this->matchedPermitPolicies,
            'matched_deny_policies' => $this->matchedDenyPolicies,
            'was_overridden_by_deny' => $this->wasOverriddenByDeny(),
            'execution_time_us' => $this->executionTimeMicroseconds,
            'decision_hash' => $this->decisionHash,
            'evaluation_trace' => $this->evaluationTrace,
        ];
    }
}
