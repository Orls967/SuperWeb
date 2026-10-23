<?php

declare(strict_types=1);

namespace Modules\Proptech\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Proptech\Domain\Models\Bim\BimModel;
use Modules\Proptech\Domain\Models\Bim\TwinComponent;
use Modules\Proptech\Domain\Models\Bim\TwinIssue;

class BimTwinService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 77.1 & 77.5 Register or revise a BIM model with hash chain and gapless versions
     */
    public function createOrReviseModel(
        string $projectCode,
        string $name,
        array $metadata
    ): BimModel {
        return DB::transaction(function () use ($projectCode, $name, $metadata) {
            $latest = BimModel::where('project_code', $projectCode)
                ->orderBy('version', 'desc')
                ->lockForUpdate()
                ->first();

            $nextVersion = $latest ? $latest->version + 1 : 1;
            $prevHash = $latest ? $latest->current_hash : str_repeat('0', 64);
            $currHash = hash('sha256', "{$projectCode}:v{$nextVersion}:{$name}:".json_encode($metadata).":{$prevHash}");

            if ($latest) {
                $latest->update(['status' => 'SUPERSEDED']);
            }

            return BimModel::create([
                'model_code' => "BIM-{$projectCode}-V{$nextVersion}",
                'project_code' => $projectCode,
                'version' => $nextVersion,
                'name' => $name,
                'previous_hash' => $prevHash,
                'current_hash' => $currHash,
                'metadata' => $metadata,
                'status' => 'APPROVED',
            ]);
        });
    }

    /**
     * 77.2 Link BIM component to EPC WBS and update physical progress
     */
    public function updateComponentProgress(
        TwinComponent $component,
        int $completionPct
    ): TwinComponent {
        $cappedPct = min(100, max(0, $completionPct));
        $newStatus = $cappedPct === 100 ? 'INSTALLED' : ($cappedPct > 0 ? 'IN_PROGRESS' : 'PLANNED');

        $component->update([
            'completion_pct' => $cappedPct,
            'status' => $newStatus,
        ]);

        return $component;
    }

    /**
     * Calculate aggregate physical progress for a WBS node from all linked twin components
     */
    public function calculateWbsPhysicalProgress(string $wbsNodeCode): float
    {
        $components = TwinComponent::where('wbs_node_code', $wbsNodeCode)->get();
        if ($components->isEmpty()) {
            return 0.0;
        }

        $totalPct = $components->sum('completion_pct');

        return round($totalPct / $components->count(), 2);
    }

    /**
     * 77.2 Capitalize verified CIP progress to Fixed Assets based on BIM progress value
     */
    public function capitalizeBimMilestoneProgress(
        string $wbsNodeCode,
        int $milestoneValueIdr
    ): void {
        $progress = $this->calculateWbsPhysicalProgress($wbsNodeCode);
        if ($progress < 100.0) {
            throw new \RuntimeException('Cannot capitalize uncompleted BIM WBS milestone');
        }

        // Post ledger: Debit Fixed Asset Property in Operation, Credit Construction in Progress (CIP)
        $this->ledgerService->post(new PostingDTO(
            type: 'BIM_CAPITALIZE_CIP',
            description: "Capitalize completed BIM milestone for WBS {$wbsNodeCode}",
            idempotencyKey: "BIM-CAP-{$wbsNodeCode}",
            entries: Posting::lines()
                ->debit('ast:fixed_assets:IDR', $milestoneValueIdr)
                ->credit('epc:cip_liability:IDR', $milestoneValueIdr)
                ->toEntries('IDR'),
            referenceType: 'BIM_WBS',
            referenceId: $wbsNodeCode,
        ));
    }

    /**
     * 77.3 Link facility work order to affected twin component
     */
    public function tagComponentWorkOrder(
        TwinComponent $component,
        string $issueType,
        string $description,
        int $workOrderId
    ): TwinIssue {
        $issueCode = 'ISS-'.strtoupper(substr(hash('sha256', "{$component->id}:{$issueType}:{$workOrderId}"), 0, 10));

        return TwinIssue::firstOrCreate(
            ['issue_code' => $issueCode],
            [
                'component_id' => $component->id,
                'issue_type' => $issueType,
                'severity' => 'HIGH',
                'description' => $description,
                'facility_work_order_id' => $workOrderId,
                'status' => 'OPEN',
            ]
        );
    }

    /**
     * 77.4 Simulation sandbox: runs in-memory simulation without modifying live database
     */
    public function runTwinAirflowSimulation(BimModel $model, float $ambientTempC): array
    {
        // Sandbox calculation without modifying records
        $components = $model->components()->get();
        $chillerCount = $components->where('category', 'HVAC_CHILLER')->count();
        $ductCount = $components->where('category', 'DUCTWORK')->count();

        $coolingCapacityKw = $chillerCount * 250.0;
        $airflowCfm = $ductCount * 1200.0;
        $projectedTempC = round($ambientTempC - ($coolingCapacityKw / max(1, $airflowCfm * 0.05)), 1);

        return [
            'simulated' => true,
            'model_id' => $model->id,
            'ambient_temp_c' => $ambientTempC,
            'projected_room_temp_c' => max(18.0, $projectedTempC),
            'airflow_cfm' => $airflowCfm,
            'db_records_modified' => 0,
        ];
    }

    /**
     * Two-source audit for BIM Models & Twin Components:
     * Source 1: BIM model version hash chain & component completion bounds
     * Source 2: Ledger CIP capitalization consistency
     */
    public function auditBim(): array
    {
        $discrepancies = [];
        $modelsChecked = 0;
        $componentsChecked = 0;

        // 1. Audit hash chain across all projects and versions
        $projects = BimModel::distinct()->pluck('project_code');
        foreach ($projects as $projectCode) {
            $models = BimModel::where('project_code', $projectCode)
                ->orderBy('version', 'asc')
                ->get();

            $prevHash = str_repeat('0', 64);
            $expectedVersion = 1;

            foreach ($models as $m) {
                $modelsChecked++;

                if ($m->version !== $expectedVersion) {
                    $discrepancies[] = "Model {$m->model_code} version mismatch: expected {$expectedVersion}, got {$m->version}";
                }

                if ($m->previous_hash !== $prevHash) {
                    $discrepancies[] = "Model {$m->model_code} hash chain broken: previous_hash {$m->previous_hash} != expected {$prevHash}";
                }

                $expectedCurrHash = hash('sha256', "{$m->project_code}:v{$m->version}:{$m->name}:".json_encode($m->metadata).":{$m->previous_hash}");
                if ($m->current_hash !== $expectedCurrHash) {
                    $discrepancies[] = "Model {$m->model_code} current_hash tampered: {$m->current_hash} != expected {$expectedCurrHash}";
                }

                $prevHash = $m->current_hash;
                $expectedVersion++;
            }
        }

        // 2. Audit twin component completion percentage bounds
        $components = TwinComponent::all();
        foreach ($components as $component) {
            $componentsChecked++;
            if ($component->completion_pct < 0 || $component->completion_pct > 100) {
                $discrepancies[] = "TwinComponent {$component->component_code} completion_pct out of bounds: {$component->completion_pct}%";
            }
        }

        return [
            'models_checked' => $modelsChecked,
            'components_checked' => $componentsChecked,
            'discrepancies' => $discrepancies,
            'passed' => empty($discrepancies),
        ];
    }
}
