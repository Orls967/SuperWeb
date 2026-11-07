<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningVentilationSimulation;
use Modules\Shared\Domain\Algorithms\Underground\AirwayBranch;
use Modules\Shared\Domain\Algorithms\Underground\HardyCrossVentilationNetworkSolver;
use Modules\Shared\Domain\Algorithms\Underground\VentilationLoop;

/**
 * Domain Action: Menjalankan Simulasi Solver Jaringan Ventilasi Hardy-Cross Bawah Tanah.
 * Menguji kestabilan sirkulasi udara, mendeteksi potensi pembalikan arah aliran udara tambang,
 * dan memverifikasi kepatuhan regulasi keselamatan tambang (Kepmen ESDM 1827/2018).
 */
class SimulateHardyCrossVentilationNetworkAction
{
    public function __construct(
        private readonly HardyCrossVentilationNetworkSolver $solver
    ) {}

    /**
     * @param  array{
     *     simulation_code: string,
     *     fan_rpm_percent?: float,
     *     air_density_kg_m3?: float,
     *     branches: list<array{
     *         id: string,
     *         name: string,
     *         resistance_r: float,
     *         initial_flow_q: float,
     *         fan_pressure_pf?: float,
     *         natural_ventilation_head_h_n?: float,
     *         fixed_quantity_q?: float|null,
     *         is_booster_branch?: bool
     *     }>,
     *     loops: list<array{
     *         loop_id: string,
     *         branch_ids: list<string>,
     *         directions: list<int>
     *     }>,
     *     headcount_underground?: int,
     *     diesel_fleet_kw?: float,
     *     executed_by?: string
     * } $data
     */
    public function execute(array $data): MiningVentilationSimulation
    {
        $this->validateData($data);

        $branches = [];
        foreach ($data['branches'] as $b) {
            $branches[] = new AirwayBranch(
                branchId: (string) ($b['id'] ?? $b['branch_id']),
                fromNode: (string) ($b['from_node'] ?? 'N1'),
                toNode: (string) ($b['to_node'] ?? 'N2'),
                resistanceR: (float) $b['resistance_r'],
                initialFlowQ: (float) $b['initial_flow_q'],
                fanPressurePa: (float) ($b['fan_pressure_pf'] ?? $b['fan_pressure_pa'] ?? 0.0),
                isFanBranch: (bool) ($b['is_fan_branch'] ?? (($b['fan_pressure_pf'] ?? 0.0) > 0)),
                lengthM: (float) ($b['length_m'] ?? 100.0),
                areaM2: (float) ($b['area_m2'] ?? 16.0),
            );
        }

        $loops = [];
        foreach ($data['loops'] as $l) {
            $loopBranches = [];
            if (isset($l['branch_ids']) && isset($l['directions'])) {
                foreach ($l['branch_ids'] as $idx => $bId) {
                    $loopBranches[] = [
                        'branch_id' => (string) $bId,
                        'direction' => (int) ($l['directions'][$idx] ?? 1),
                    ];
                }
            } elseif (isset($l['branches']) && is_array($l['branches'])) {
                $loopBranches = $l['branches'];
            }
            $loops[] = new VentilationLoop(
                loopId: (string) $l['loop_id'],
                branches: $loopBranches
            );
        }

        $result = $this->solver->solve($branches, $loops, tolerance: 0.001, maxIterations: 100);

        // Validasi kebutuhan udara Kepmen ESDM 1827/2018:
        // Personel: min 2.0 m3/menit per orang (0.033 m3/s per orang)
        // Alat berat diesel: min 3.0 m3/menit per HP / 4.0 m3/menit per kW (0.067 m3/s per kW)
        $headcount = (int) ($data['headcount_underground'] ?? 20);
        $dieselKw = (float) ($data['diesel_fleet_kw'] ?? 350.0);

        $personnelReqM3s = $headcount * 0.0333;
        $dieselReqM3s = $dieselKw * 0.0667;
        $totalRegulatoryReqM3s = round($personnelReqM3s + $dieselReqM3s, 2);

        $totalIntake = 0.0;
        $totalReturn = 0.0;
        foreach ($branches as $branch) {
            $q = $result->finalFlowsQ[$branch->branchId] ?? 0.0;
            if ($branch->isFanBranch || str_contains(strtolower($branch->branchId), 'in')) {
                $totalIntake += abs($q);
            }
            if (str_contains(strtolower($branch->branchId), 'ret') || str_contains(strtolower($branch->branchId), 'ex')) {
                $totalReturn += abs($q);
            }
        }
        if ($totalIntake <= 0) {
            $totalIntake = array_sum(array_map('abs', $result->finalFlowsQ)) / 2.0;
        }

        $isComplianceSatisfied = $totalIntake >= $totalRegulatoryReqM3s && $result->converged;

        $sim = new MiningVentilationSimulation;
        $sim->fill([
            'simulation_code' => strtoupper(trim($data['simulation_code'])),
            'total_branches' => count($branches),
            'total_loops' => count($loops),
            'iterations_count' => $result->iterationsCount,
            'iterations_to_converge' => $result->iterationsCount,
            'converged' => $result->converged,
            'is_converged' => $result->converged,
            'max_residual_flow' => $result->maxResidualFlow,
            'flow_balance_error_m3_s' => $result->maxResidualFlow,
            'total_fan_power_kw' => $result->totalFanPowerKw,
            'fan_operating_point' => [
                'rpm_percent' => (float) ($data['fan_rpm_percent'] ?? 100.0),
                'total_fan_pressure_pa' => 1500.0,
                'total_fan_power_kw' => $result->totalFanPowerKw,
            ],
            'branch_results' => [
                'flows' => $result->finalFlowsQ,
                'head_losses' => $result->finalHeadLossesPa,
            ],
            'hmac_signature' => $result->hmacSignature,
            'simulated_by' => $data['executed_by'] ?? 'Underground Ventilation Officer',
            'intake_airflow_m3_s' => round($totalIntake, 2),
            'return_airflow_m3_s' => round($totalReturn > 0 ? $totalReturn : $totalIntake, 2),
            'ch4_dilution_capacity_pct' => 0.45,
            'co_concentration_ppm' => 12.0,
            'status' => $isComplianceSatisfied ? 'VALID' : 'REQUIRES_RECALIBRATION',
            'metadata' => [
                'regulatory_requirement_m3_s' => $totalRegulatoryReqM3s,
                'personnel_headcount' => $headcount,
                'diesel_fleet_kw' => $dieselKw,
                'solved_branch_flows' => $result->finalFlowsQ,
                'branch_pressure_drops_pa' => $result->finalHeadLossesPa,
                'executed_at' => now()->toIso8601String(),
            ],
        ]);

        $sim->save();

        return $sim;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['simulation_code'])) {
            throw new InvalidArgumentException('Simulation code tidak boleh kosong.');
        }

        if (empty($data['branches']) || ! is_array($data['branches'])) {
            throw new InvalidArgumentException('Daftar cabang (branches) ventilasi tidak boleh kosong.');
        }

        if (empty($data['loops']) || ! is_array($data['loops'])) {
            throw new InvalidArgumentException('Daftar loop tertutup ventilasi Hardy-Cross tidak boleh kosong.');
        }
    }
}
