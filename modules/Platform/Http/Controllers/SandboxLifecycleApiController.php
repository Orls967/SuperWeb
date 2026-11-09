<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Platform\Domain\Actions\CreateSandboxAction;
use Modules\Platform\Domain\Actions\DestroySandboxAction;
use Modules\Platform\Domain\Actions\ProvisionSandboxAction;
use Modules\Platform\Domain\Enums\SandboxTier;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Http\Requests\CreateSandboxRequest;

class SandboxLifecycleApiController extends Controller
{
    public function store(CreateSandboxRequest $request, CreateSandboxAction $action): JsonResponse
    {
        $tier = SandboxTier::from($request->input('tier'));
        $sandbox = $action->execute(
            name: (string) $request->input('name'),
            ownerId: (int) $request->user()->id,
            tier: $tier,
            idempotencyKey: $request->input('idempotency_key'),
            metadata: $request->input('metadata', [])
        );

        return response()->json([
            'status' => 'success',
            'data' => $sandbox,
        ], 201);
    }

    public function provision(SandboxEnvironment $sandbox, ProvisionSandboxAction $action): JsonResponse
    {
        $this->authorize('update', $sandbox);

        $provisioned = $action->execute($sandbox);

        return response()->json([
            'status' => 'success',
            'data' => $provisioned,
            'provisioned_at' => now()->toIso8601String(),
        ]);
    }

    public function destroy(SandboxEnvironment $sandbox, DestroySandboxAction $action): JsonResponse
    {
        $this->authorize('delete', $sandbox);

        $action->execute($sandbox);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $sandbox->id,
                'slug' => $sandbox->slug,
                'decommissioned' => true,
            ],
        ]);
    }
}
