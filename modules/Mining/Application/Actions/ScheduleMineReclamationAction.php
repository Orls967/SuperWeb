<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningReclamationSchedule;
use Modules\Mining\Domain\Models\MiningSite;

class ScheduleMineReclamationAction
{
    /**
     * Jadwalkan program reklamasi dan revegetasi lahan bekas tambang (D94-6).
     *
     * @param array{
     *     site_id: string,
     *     area_name: string,
     *     target_hectares: float,
     *     planned_year: int,
     *     planned_quarter: int,
     *     estimated_cost_idr: int,
     *     escrow_bank?: string,
     *     reclamation_activity_type?: string
     * } $data
     */
    public function execute(array $data): MiningReclamationSchedule
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $areaName = trim((string) $data['area_name']);
            $hectares = (float) $data['target_hectares'];
            $year = (int) $data['planned_year'];
            $quarter = (int) $data['planned_quarter'];
            $costIdr = (int) $data['estimated_cost_idr'];

            if ($hectares <= 0 || $costIdr <= 0) {
                throw new InvalidArgumentException('Luas hektar dan estimasi biaya reklamasi harus positif.');
            }

            if ($quarter < 1 || $quarter > 4) {
                throw new InvalidArgumentException('Kuartal rencana harus di antara 1 dan 4.');
            }

            $site = MiningSite::findOrFail($siteId);

            $projectNumber = sprintf('REC-PRJ-%s-%d-Q%d', $site->site_code ?? 'SITE', $year, $quarter);

            $schedule = MiningReclamationSchedule::firstOrNew([
                'site_id' => $site->id,
                'planned_year' => $year,
                'planned_quarter' => $quarter,
            ]);

            if (! $schedule->exists) {
                $schedule->id = (string) Str::uuid();
            }

            $schedule->fill([
                'project_number' => $projectNumber,
                'phase_name' => $areaName,
                'target_area_hectares' => (int) round($hectares),
                'provision_amount_minor' => $costIdr,
                'status' => 'SCHEDULED',
            ])->save();

            return $schedule;
        });
    }
}
