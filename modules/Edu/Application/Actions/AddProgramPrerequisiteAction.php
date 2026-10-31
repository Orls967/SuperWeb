<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Edu\Domain\Exceptions\PrerequisiteCycleException;
use Modules\Edu\Domain\Models\EduPrerequisite;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\Numerics\DagValidator;

final class AddProgramPrerequisiteAction extends BaseAction
{
    /**
     * Adds a curriculum prerequisite link with cycle detection (B0''.10 DagValidator).
     *
     * @param  array{program_id: string, prerequisite_program_id: string}|EduProgram  $program
     */
    public function execute(array|EduProgram $program, EduProgram|string|null $prerequisiteProgram = null): EduPrerequisite
    {
        if (is_array($program)) {
            $programId = (string) $program['program_id'];
            $prerequisiteProgramId = (string) $program['prerequisite_program_id'];
        } elseif ($program instanceof EduProgram && $prerequisiteProgram !== null) {
            $programId = (string) $program->id;
            $prerequisiteProgramId = $prerequisiteProgram instanceof EduProgram ? (string) $prerequisiteProgram->id : (string) $prerequisiteProgram;
        } else {
            throw new InvalidArgumentException('Invalid arguments provided to AddProgramPrerequisiteAction.');
        }

        if ($programId === $prerequisiteProgramId) {
            throw new PrerequisiteCycleException("Self-referencing prerequisite creates immediate cycle: {$programId} -> {$prerequisiteProgramId}");
        }

        // Idempotency: return existing prerequisite
        $existing = EduPrerequisite::where('program_id', $programId)
            ->where('prerequisite_program_id', $prerequisiteProgramId)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Build adjacency graph for cycle detection: dependent -> prerequisite
        $allPrereqs = EduPrerequisite::all();
        $adjList = [];

        foreach ($allPrereqs as $p) {
            $u = (string) $p->program_id;
            $v = (string) $p->prerequisite_program_id;
            $adjList[$u][] = $v;
            if (! isset($adjList[$v])) {
                $adjList[$v] = [];
            }
        }

        // Add proposed candidate edge
        $adjList[$programId][] = $prerequisiteProgramId;
        if (! isset($adjList[$prerequisiteProgramId])) {
            $adjList[$prerequisiteProgramId] = [];
        }

        // Cycle check via DagValidator
        $cycle = DagValidator::findCycle($adjList);
        if ($cycle !== null) {
            throw new PrerequisiteCycleException('Prerequisite cycle detected in curriculum graph: '.implode(' -> ', $cycle));
        }

        return $this->transaction(function () use ($programId, $prerequisiteProgramId) {
            return EduPrerequisite::create([
                'id' => (string) Str::uuid(),
                'program_id' => $programId,
                'prerequisite_program_id' => $prerequisiteProgramId,
            ]);
        });
    }

    /**
     * Retrieves all transitive prerequisites for a program.
     *
     * @return list<string>
     */
    public function getTransitivePrerequisites(string $programId): array
    {
        $allPrereqs = EduPrerequisite::all();
        $adjList = [];

        foreach ($allPrereqs as $p) {
            $u = (string) $p->program_id;
            $v = (string) $p->prerequisite_program_id;
            $adjList[$u][] = $v;
            if (! isset($adjList[$v])) {
                $adjList[$v] = [];
            }
        }

        if (! isset($adjList[$programId])) {
            return [];
        }

        return DagValidator::transitivePrerequisites($programId, $adjList);
    }
}
