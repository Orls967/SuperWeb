<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Policies;

use App\Models\User;
use Modules\Aviation\Domain\Models\AviationCargoConsignment;

class AviationCargoPolicy
{
    /**
     * Tentukan apakah pengguna dapat melihat rincian konsinyasi kargo udara.
     */
    public function view(User $user, AviationCargoConsignment $consignment): bool
    {
        // Admin, ops_controller, dan cargo_agent dapat melihat seluruh kargo
        if ($user->hasRole(['admin', 'ops_controller', 'cargo_agent', 'superadmin'])) {
            return true;
        }

        // Shipper atau Consignee hanya dapat melihat kargo miliknya sendiri (IDOR Guard)
        return (int) $consignment->shipper_party_id === (int) $user->id
            || (int) $consignment->consignee_party_id === (int) $user->id;
    }

    /**
     * Tentukan apakah pengguna dapat memperbarui status atau menerima kargo.
     */
    public function manage(User $user): bool
    {
        return $user->hasRole(['admin', 'cargo_agent', 'superadmin']);
    }
}

