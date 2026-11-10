<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use App\Models\User;
use Modules\Core\Domain\Models\CoreTask;

/**
 * Service perutean tugas cerdas berbasis keahlian dan beban staf (Smart Task Router).
 */
class SmartTaskRouter
{
    /**
     * Peta peran spesialis berdasarkan modul sumber tugas.
     */
    private const MODULE_ROLE_ROUTING = [
        'Finance' => 'accountant',
        'AutoServe' => 'mechanic',
        'Contract' => 'legal_officer',
        'Asset' => 'asset_manager',
        'Marine' => 'marine_dispatcher',
        'Procurement' => 'purchaser',
        'Manufacture' => 'plant_operator',
        'Hotel' => 'hotel_manager',
        'Flight' => 'flight_controller',
        'Logistics' => 'logistics_supervisor',
    ];

    /**
     * Menentukan assignee terbaik untuk tugas baru.
     *
     * @param  array{module: string, priority?: string, source_entity_type?: string}  $taskAttributes
     */
    public function routeTask(array $taskAttributes): array
    {
        $module = $taskAttributes['module'] ?? 'Core';
        $recommendedRole = self::MODULE_ROLE_ROUTING[$module] ?? 'operator';

        // Temukan kandidat pengguna dengan role tersebut
        $candidates = User::query()->where('role', $recommendedRole)->get();

        if ($candidates->isEmpty()) {
            // Fallback ke supervisor atau admin
            $fallbackUser = User::query()->whereIn('role', ['supervisor', 'admin'])->first();

            return [
                'assignee_user_id' => $fallbackUser?->id,
                'assignee_role' => $fallbackUser?->role ?? 'admin',
                'strategy' => 'fallback_supervisor',
            ];
        }

        // Cari kandidat dengan beban tugas pending terendah
        $candidateIds = $candidates->pluck('id')->toArray();
        $loads = CoreTask::query()
            ->whereIn('assignee_user_id', $candidateIds)
            ->whereIn('status', ['pending', 'in_progress'])
            ->selectRaw('assignee_user_id, count(*) as cnt')
            ->groupBy('assignee_user_id')
            ->pluck('cnt', 'assignee_user_id')
            ->toArray();

        $selectedUser = null;
        $lowestLoad = PHP_INT_MAX;

        foreach ($candidates as $user) {
            $userLoad = $loads[$user->id] ?? 0;
            if ($userLoad < $lowestLoad) {
                $lowestLoad = $userLoad;
                $selectedUser = $user;
            }
        }

        return [
            'assignee_user_id' => $selectedUser?->id,
            'assignee_role' => $recommendedRole,
            'strategy' => 'least_loaded_specialist',
        ];
    }
}
