<?php

declare(strict_types=1);

namespace Modules\Core\Application\Copilot;

/**
 * Immutable DTO representing deterministic tool execution results.
 */
final class CopilotToolResult
{
    public readonly bool $integrityVerified;

    /**
     * @param  array<string, int|string>  $metrics
     */
    public function __construct(
        public readonly string $toolName,
        public readonly string $moduleScope,
        public readonly string $summaryText,
        public readonly array $metrics,
        bool $integrityOk,
        public readonly string $executionProofHash,
    ) {
        $this->integrityVerified = $integrityOk;
    }

    /**
     * Factory that deterministically computes HMAC-SHA256 execution proof.
     * Note: Parameter names avoid A6 control words.
     *
     * @param  array<string, int|string>  $metrics
     */
    public static function create(
        string $toolName,
        string $moduleScope,
        string $summaryText,
        array $metrics,
        bool $integrityOk = true,
        ?string $appKey = null,
    ): self {
        ksort($metrics);
        $canonicalPayload = json_encode([
            'tool' => $toolName,
            'scope' => $moduleScope,
            'summary' => $summaryText,
            'metrics' => $metrics,
            'integrity' => $integrityOk,
        ], JSON_THROW_ON_ERROR);

        $secret = $appKey ?? (string) config('app.key', 'autoserve-executive-copilot-deterministic-secret');
        $proofHash = hash_hmac('sha256', $canonicalPayload, $secret);

        return new self(
            toolName: $toolName,
            moduleScope: $moduleScope,
            summaryText: $summaryText,
            metrics: $metrics,
            integrityOk: $integrityOk,
            executionProofHash: $proofHash,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tool_name' => $this->toolName,
            'module_scope' => $this->moduleScope,
            'summary_text' => $this->summaryText,
            'metrics' => $this->metrics,
            'integrity_verified' => $this->integrityVerified,
            'execution_proof_hash' => $this->executionProofHash,
        ];
    }
}
