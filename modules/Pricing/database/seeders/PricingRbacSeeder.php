<?php

declare(strict_types=1);

namespace Modules\Pricing\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

/**
 * Idempotently seeds RBAC roles and permissions for Pricing (P2.1).
 */
class PricingRbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        // 1. Ensure role pricing_manager
        $pricingManager = $svc->ensureRole(
            'pricing_manager',
            'Pricing Manager',
            'Pengelola strategi harga dinamis, guardrail harga, surge pricing, dan penolakan tick.'
        );

        // 2. Ensure permissions
        $permissions = [
            ['name' => 'pricing.surge.view', 'label' => 'Lihat Dashboard Surge Pricing'],
            ['name' => 'pricing.surge.calculate', 'label' => 'Kalkulasi & Simulasi Surge Pricing'],
            ['name' => 'pricing.rejections.view', 'label' => 'Lihat Log Penolakan Guardrail Pricing'],
        ];

        foreach ($permissions as $p) {
            $svc->ensurePermission($p['name'], $p['label'], 'pricing');
        }

        // 3. Grant permissions to pricing_manager
        $svc->grantPermissionsToRole($pricingManager, [
            'pricing.surge.view',
            'pricing.surge.calculate',
            'pricing.rejections.view',
        ]);
    }
}
