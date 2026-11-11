<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Application\Api\EdiProcessingService;
use Modules\Core\Application\Tenancy\TenantContext;

class ApiV2EdiController extends Controller
{
    public function __construct(
        private readonly EdiProcessingService $ediService,
    ) {}

    public function interchange(Request $request): JsonResponse
    {
        $rawPayload = (string) $request->getContent();
        if (trim($rawPayload) === '') {
            return response()->json([
                'type' => 'https://superweb.example.com/probs/empty-payload',
                'title' => 'Empty Interchange Payload',
                'status' => 400,
                'detail' => 'EDI payload is required in request body.',
            ], 400);
        }

        $idempKey = (string) $request->header('Idempotency-Key', 'edi-'.uniqid());
        $context = TenantContext::current() ?? new TenantContext('tenant-default');

        $interchange = $this->ediService->processInterchange(
            $context->tenantId,
            $rawPayload,
            $idempKey,
            Auth::id()
        );

        return response()->json([
            'id' => $interchange->id,
            'control_ref' => $interchange->control_ref,
            'standard' => $interchange->standard,
            'message_type' => $interchange->message_type,
            'status' => $interchange->status,
            'target_reference' => $interchange->target_reference,
            'acknowledgment_payload' => $interchange->contrl_payload,
            'cert_hash' => $interchange->cert_hash,
        ], 201);
    }
}
