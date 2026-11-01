<?php

declare(strict_types=1);

namespace Modules\Edu\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Services\CertificateQrSigner;

final class PublicCertificateVerificationController extends Controller
{
    public function __construct(
        private readonly CertificateQrSigner $signer
    ) {}

    public function verifyApi(Request $request): JsonResponse
    {
        $token = (string) $request->query('token', '');
        if ($token === '') {
            return response()->json(['message' => 'Token parameter is required.'], 422);
        }

        if (! $this->signer->verifyToken($token)) {
            return response()->json(['message' => 'Invalid or tampered verification token.'], 422);
        }

        $parsed = $this->signer->parseToken($token);
        if ($parsed === null) {
            return response()->json(['message' => 'Malformed verification token.'], 422);
        }

        $cert = EduCertificate::with('program')->find($parsed['uuid']);
        if (! $cert) {
            return response()->json(['message' => 'Certificate not found.'], 404);
        }

        $status = $cert->status;
        if ($status === 'ACTIVE' && $cert->expires_at && Carbon::parse($cert->expires_at)->isPast()) {
            $status = 'EXPIRED';
        }

        return response()->json([
            'data' => [
                'status' => $status,
                'certificate_number' => $cert->certificate_number,
                'program_code' => $cert->program ? $cert->program->program_code : $cert->skill_competency_code,
                'program_title' => $cert->program ? $cert->program->title : 'Professional Competency Track',
                'issued_at' => $cert->issued_at ? Carbon::parse($cert->issued_at)->toDateString() : null,
                'expires_at' => $cert->expires_at ? Carbon::parse($cert->expires_at)->toDateString() : null,
            ],
        ]);
    }

    public function verifyWeb(Request $request): View
    {
        $token = (string) $request->query('token', '');
        if ($token === '') {
            abort(404, 'Token required');
        }

        $cert = EduCertificate::where('qr_token', $token)->first();
        if (! $cert) {
            $parsed = $this->signer->parseToken($token);
            if ($parsed) {
                $cert = EduCertificate::with('program')->find($parsed['uuid']);
            }
        }

        if (! $cert) {
            abort(404, 'Certificate not found');
        }

        $status = $cert->status;
        if ($status === 'ACTIVE' && $cert->expires_at && Carbon::parse($cert->expires_at)->isPast()) {
            $status = 'EXPIRED';
        }

        return view('edu::certificate_verify', [
            'cert' => $cert,
            'status' => $status,
        ]);
    }
}
