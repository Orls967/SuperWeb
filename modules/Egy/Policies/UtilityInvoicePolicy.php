<?php

declare(strict_types=1);

namespace Modules\Egy\Policies;

use App\Models\User;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;

class UtilityInvoicePolicy
{
    public function view(User $user, UtilityConsolidatedInvoice $invoice): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('energy_billing') || $user->hasRole('energy_auditor')) {
            return true;
        }

        if ($user->hasRole('customer')) {
            return (string) $invoice->customer_party_id === (string) $user->id
                || (string) $invoice->customer_party_id === (string) ($user->party_id ?? '');
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('energy_billing');
    }

    public function update(User $user, UtilityConsolidatedInvoice $invoice): bool
    {
        return $user->hasRole('admin') || $user->hasRole('energy_billing');
    }
}
