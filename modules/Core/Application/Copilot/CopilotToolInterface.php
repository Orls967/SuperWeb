<?php

declare(strict_types=1);

namespace Modules\Core\Application\Copilot;

/**
 * Deterministic tool interface for the Enterprise AI Executive Copilot.
 */
interface CopilotToolInterface
{
    /**
     * Unique identifier of the tool.
     */
    public function name(): string;

    /**
     * Natural description of tool capabilities.
     */
    public function description(): string;

    /**
     * Cross-module scope monitored by this tool.
     */
    public function moduleScope(): string;

    /**
     * Deterministically check if the tool can handle or contributes to the prompt.
     */
    public function canHandle(string $prompt): bool;

    /**
     * Execute the deterministic tool and return an immutable CopilotToolResult.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function execute(array $parameters = []): CopilotToolResult;
}
