<?php

declare(strict_types=1);

namespace Modules\Core\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

class SimulationRbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        // Ensure roles
        $simOperator = $svc->ensureRole(
            'sim_operator',
            'Simulation Operator',
            'Operator orkestrasi waktu, kernel simulasi deterministik, dan digital twin bus (Fase 67).'
        );

        $spineOperator = $svc->ensureRole(
            'spine_operator',
            'Event Spine Operator',
            'Operator pemantau tulang punggung event spine, offset consumer group, dan konsol DLQ (Fase R8).'
        );

        // Ensure permissions
        $permissions = [
            ['name' => 'sim.runs.view', 'label' => 'Lihat Sesi Simulasi'],
            ['name' => 'sim.runs.manage', 'label' => 'Kelola & Eksekusi Simulasi'],
            ['name' => 'sim.diff.view', 'label' => 'Bandingkan Simpangan Simulasi'],
            ['name' => 'sim.twins.view', 'label' => 'Lihat Digital Twins'],
            ['name' => 'spine.messages.view', 'label' => 'Lihat Pesan Event Spine'],
            ['name' => 'spine.dlq.manage', 'label' => 'Kelola & Replay Pesan DLQ'],
            ['name' => 'spine.consumers.view', 'label' => 'Lihat Consumer Group Lag'],
            ['name' => 'spine.schemas.view', 'label' => 'Lihat Schema Registry'],
        ];

        foreach ($permissions as $p) {
            $svc->ensurePermission($p['name'], $p['label'], 'core_simulation');
        }

        // Grant permissions to sim_operator
        $svc->grantPermissionsToRole($simOperator, [
            'sim.runs.view',
            'sim.runs.manage',
            'sim.diff.view',
            'sim.twins.view',
        ]);

        // Grant permissions to spine_operator
        $svc->grantPermissionsToRole($spineOperator, [
            'spine.messages.view',
            'spine.dlq.manage',
            'spine.consumers.view',
            'spine.schemas.view',
        ]);
    }
}
