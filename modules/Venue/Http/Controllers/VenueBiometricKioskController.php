<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Venue\Application\Services\BiometricPrivacyVaultService;
use Modules\Venue\Application\Services\TurnstileHardwareControllerService;
use Modules\Venue\Domain\Models\BiometricCredential;
use Modules\Venue\Domain\Models\GateTurnstile;

class VenueBiometricKioskController extends Controller
{
    public function __construct(
        private readonly BiometricPrivacyVaultService $vaultService,
        private readonly TurnstileHardwareControllerService $turnstileService
    ) {}

    public function kiosk(Request $request): View
    {
        $gateCode = (string) $request->input('gate_code', 'GATE-MAIN-01');
        $turnstiles = GateTurnstile::query()->get();
        $userCredential = BiometricCredential::where('user_id', auth()->id())->first();

        return view('venue::biometrics.kiosk', [
            'gateCode' => $gateCode,
            'turnstiles' => $turnstiles,
            'userCredential' => $userCredential,
        ]);
    }

    public function enrolment(Request $request): View
    {
        $credential = BiometricCredential::where('user_id', auth()->id())->first();

        return view('venue::biometrics.enrolment', [
            'credential' => $credential,
        ]);
    }

    public function processEnrol(Request $request): RedirectResponse
    {
        $vector = (string) $request->input('feature_vector', 'VECTOR-FACE-SAMPLE-'.auth()->id());
        $proof = (string) $request->input('challenge_proof', 'CHALLENGE-BLINK-SUCCESS');

        $this->vaultService->enrolGuestBiometrics(
            userId: (int) auth()->id(),
            rawFeatureVector: $vector,
            challengeProof: $proof,
            sensorModel: 'KIOSK-CAM-3D'
        );

        return redirect()->route('venue.biometrics.kiosk')
            ->with('success', 'Pendaftaran Face-ID biometrik berhasil dan terenkripsi satu arah.');
    }

    public function scanGate(Request $request): JsonResponse
    {
        $userId = (int) $request->input('user_id', auth()->id());
        $gateCode = (string) $request->input('gate_code', 'GATE-MAIN-01');
        $vector = (string) $request->input('probe_vector', 'VECTOR-FACE-SAMPLE-'.$userId);
        $proof = (string) $request->input('challenge_proof', 'CHALLENGE-BLINK-SUCCESS');

        $result = $this->vaultService->scanTurnstileGate(
            userId: $userId,
            gateCode: $gateCode,
            probeFeatureVector: $vector,
            livenessProof: $proof
        );

        if ($result->accessGranted) {
            $this->turnstileService->recordSuccessfulEntry($gateCode);
        }

        return response()->json([
            'status' => $result->accessGranted ? 'ACCESS_GRANTED' : 'ACCESS_DENIED',
            'data' => $result->toArray(),
        ]);
    }

    public function revokeConsent(Request $request): RedirectResponse
    {
        $this->vaultService->revokeBiometricProfile(
            userId: (int) auth()->id(),
            revocationReason: 'Permintaan pencabutan oleh pemilik data pribadi (UU PDP)'
        );

        return redirect()->back()->with('success', 'Persetujuan data biometrik Anda telah dicabut seketika.');
    }
}
