<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Access\Values\ResourceDescriptor;

/**
 * Resource Attribute Resolver (W8-D53, W8-D54).
 *
 * Inspects Eloquent models or generic objects to synthesize ResourceDescriptor attributes.
 */
final class ResourceAttributeResolver
{
    /**
     * Resolve ResourceDescriptor from an Eloquent model instance.
     */
    public function resolveFromModel(Model $model): ResourceDescriptor
    {
        $classBasename = class_basename($model);
        $id = (string) $model->getKey();
        $tenantId = (string) ($model->getAttribute('tenant_id') ?? '0193188a-7800-7000-8000-000000000001');

        $classification = (string) ($model->getAttribute('classification') ?? ResourceDescriptor::CLASS_INTERNAL);
        $dept = $model->getAttribute('department_id') !== null ? (string) $model->getAttribute('department_id') : null;

        $hasPii = false;
        $piiAttributes = ['nik', 'npwp', 'paspor', 'email', 'phone', 'alamat', 'rek_bank'];
        foreach ($piiAttributes as $pii) {
            if ($model->getAttribute($pii) !== null) {
                $hasPii = true;
                break;
            }
        }

        $isFinancial = false;
        $financialAttributes = ['amount_idr', 'total_amount_idr', 'nominal_idr', 'balance_idr'];
        foreach ($financialAttributes as $fin) {
            $val = $model->getAttribute($fin);
            if ($val !== null && (int) $val > 500000000) {
                $isFinancial = true;
                break;
            }
        }

        $clearance = 2;
        if ($isFinancial || $hasPii) {
            $clearance = 3;
        }

        return new ResourceDescriptor(
            resourceType: strtolower($classBasename),
            resourceId: $id,
            ownerTenantId: $tenantId,
            classification: $classification,
            ownerDepartmentId: $dept,
            requiredClearance: $clearance,
            containsPii: $hasPii,
            isFinancialTranche: $isFinancial,
            resourceAttributes: $model->toArray()
        );
    }
}
