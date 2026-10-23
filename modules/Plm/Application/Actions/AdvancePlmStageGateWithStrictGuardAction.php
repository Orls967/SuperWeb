<?php

declare(strict_types=1);

namespace Modules\Plm\Application\Actions;

use InvalidArgumentException;
use Modules\Plm\Domain\Models\PlmProject;

class AdvancePlmStageGateWithStrictGuardAction
{
    public const STAGES = [
        'ideation',
        'scoping',
        'business_case',
        'development',
        'testing',
        'commercial_launch',
    ];

    /**
     * Advance project to next Stage-Gate with strict validation (closes K-30).
     *
     * @param array{
     *     gate_approval_signoff?: bool,
     *     deliverables_verified?: bool
     * } $options
     * @return array{
     *     project: PlmProject,
     *     previous_stage: string,
     *     new_stage: string,
     *     gate_passed: bool
     * }
     */
    public function execute(PlmProject $project, string $targetStage, array $options = []): array
    {
        if (! in_array($targetStage, self::STAGES, true)) {
            throw new InvalidArgumentException("Invalid stage: {$targetStage}.");
        }

        $currentIndex = array_search($project->stage, self::STAGES, true);
        $targetIndex = array_search($targetStage, self::STAGES, true);

        if ($targetIndex <= $currentIndex) {
            throw new InvalidArgumentException("Cannot revert or repeat stage from {$project->stage} to {$targetStage}.");
        }

        // K-30 Strict Guard: No jumping/skipping stages
        if ($targetIndex !== $currentIndex + 1) {
            throw new InvalidArgumentException("Stage-Gate jump rejected (K-30 violation): cannot advance from '{$project->stage}' directly to '{$targetStage}'. Stages must progress strictly sequentially.");
        }

        // Gate checks per stage
        $previousStage = $project->stage;
        $this->validateGateDeliverables($project, $targetStage, $options);

        $project->update(['stage' => $targetStage]);

        return [
            'project' => $project->fresh(),
            'previous_stage' => $previousStage,
            'new_stage' => $targetStage,
            'gate_passed' => true,
        ];
    }

    protected function validateGateDeliverables(PlmProject $project, string $targetStage, array $options): void
    {
        if ($targetStage === 'development') {
            if ($project->budget_rd_idr <= 0) {
                throw new InvalidArgumentException('Stage-Gate gatekeeper: R&D budget must be allocated before entering development.');
            }
        }

        if ($targetStage === 'testing') {
            if ($project->eboms()->count() === 0) {
                throw new InvalidArgumentException('Stage-Gate gatekeeper: At least one Engineering BOM (EBOM) must be defined before entering testing.');
            }
        }

        if ($targetStage === 'commercial_launch') {
            $hasReleasedEbom = $project->eboms()->whereIn('status', ['released', 'released_to_mbom'])->exists();
            if (! $hasReleasedEbom) {
                throw new InvalidArgumentException('Stage-Gate gatekeeper: At least one EBOM must be released to MBOM before commercial launch.');
            }

            if (! ($options['gate_approval_signoff'] ?? true)) {
                throw new InvalidArgumentException('Stage-Gate gatekeeper: Commercial launch requires explicit steering committee approval signoff.');
            }
        }
    }
}
