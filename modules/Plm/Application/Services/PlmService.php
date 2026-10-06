<?php

declare(strict_types=1);

namespace Modules\Plm\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Plm\Domain\Models\ChangeOrder;
use Modules\Plm\Domain\Models\EngineeringBom;
use Modules\Plm\Domain\Models\LabNotebook;
use Modules\Plm\Domain\Models\PlmProject;

class PlmService
{
    public function createProject(array $data): PlmProject
    {
        return DB::transaction(function () use ($data) {
            return PlmProject::create([
                'id' => (string) Str::uuid(),
                'code' => $data['code'] ?? 'PRJ-'.strtoupper(Str::random(6)),
                'name' => $data['name'],
                'stage' => $data['stage'] ?? 'ideation',
                'budget_rd_idr' => (int) ($data['budget_rd_idr'] ?? 100000000),
                'projected_roi_percent' => $data['projected_roi_percent'] ?? 25.5,
                'status' => 'active',
            ]);
        });
    }

    public function createEngineeringBom(PlmProject $project, string $bomNumber, array $components): EngineeringBom
    {
        return DB::transaction(function () use ($project, $bomNumber, $components) {
            return EngineeringBom::create([
                'id' => (string) Str::uuid(),
                'project_id' => $project->id,
                'bom_number' => $bomNumber,
                'version' => 'v1.0',
                'components' => $components,
                'status' => 'draft',
            ]);
        });
    }

    public function submitEngineeringChangeOrder(EngineeringBom $ebom, string $title, string $reason, int $costImpactIdr, string $disposition = 'scrap'): ChangeOrder
    {
        return DB::transaction(function () use ($ebom, $title, $reason, $costImpactIdr, $disposition) {
            $lastEco = ChangeOrder::where('ebom_id', $ebom->id)->latest()->first();
            $prevHash = $lastEco ? $lastEco->hash : 'GENESIS-ECO-'.hash('sha256', $ebom->id);

            $payload = "{$prevHash}|{$ebom->id}|{$title}|{$costImpactIdr}|{$disposition}";
            $hash = hash('sha256', $payload);

            return ChangeOrder::create([
                'id' => (string) Str::uuid(),
                'eco_number' => 'ECO-'.strtoupper(Str::random(8)),
                'ebom_id' => $ebom->id,
                'title' => $title,
                'reason' => $reason,
                'cost_impact_idr' => $costImpactIdr,
                'disposition' => $disposition,
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'status' => 'approved',
            ]);
        });
    }

    public function recordLabExperiment(PlmProject $project, string $code, string $title, string $secretFormula, string $stability = 'pass', float $sensoryScore = 8.5): LabNotebook
    {
        return DB::transaction(function () use ($project, $code, $title, $secretFormula, $stability, $sensoryScore) {
            return LabNotebook::create([
                'id' => (string) Str::uuid(),
                'project_id' => $project->id,
                'experiment_code' => $code,
                'title' => $title,
                'formula_payload_encrypted' => base64_encode($secretFormula),
                'stability_test_result' => $stability,
                'sensory_score' => $sensoryScore,
            ]);
        });
    }

    public function auditPlm(): array
    {
        $ecos = ChangeOrder::all();
        $discrepancies = 0;

        foreach ($ecos as $eco) {
            $expectedPayload = "{$eco->prev_hash}|{$eco->ebom_id}|{$eco->title}|{$eco->cost_impact_idr}|{$eco->disposition}";
            $expectedHash = hash('sha256', $expectedPayload);

            if ($eco->hash !== $expectedHash) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'project_count' => PlmProject::count(),
            'ebom_count' => EngineeringBom::count(),
            'eco_count' => $ecos->count(),
            'discrepancies' => $discrepancies,
        ];
    }
}
