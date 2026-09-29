<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Actions\VerifyPassportAction;
use Modules\Core\Domain\Models\Vehicle;

class PassportController extends Controller
{
    public function __construct(
        private readonly VerifyPassportAction $verifyPassportAction
    ) {}

    public function show(Request $request, string $uuid): View
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Tautan verifikasi paspor tidak valid atau telah kedaluwarsa.');
        }

        $vehicle = Vehicle::with(['car.brand', 'user', 'events' => fn ($q) => $q->orderBy('sequence')])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $verificationResult = $this->verifyPassportAction->execute($vehicle);

        // Mask owner personal data for public privacy
        $ownerName = $vehicle->user ? $this->maskName($vehicle->user->name) : 'Anonim';
        $ownerPhone = $vehicle->user?->phone ? $this->maskPhone($vehicle->user->phone) : '-';

        // Generate QR code SVG
        $currentUrl = $request->fullUrl();
        $renderer = new ImageRenderer(
            new RendererStyle(180),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString($currentUrl);

        return view('core::passport.show', [
            'vehicle' => $vehicle,
            'verification' => $verificationResult,
            'ownerName' => $ownerName,
            'ownerPhone' => $ownerPhone,
            'qrSvg' => $qrSvg,
            'events' => $vehicle->events,
        ]);
    }

    private function maskName(string $name): string
    {
        $parts = explode(' ', trim($name));
        $masked = array_map(function ($part) {
            $len = mb_strlen($part);
            if ($len <= 2) {
                return $part[0].'*';
            }

            return mb_substr($part, 0, 1).str_repeat('*', $len - 2).mb_substr($part, -1);
        }, $parts);

        return implode(' ', $masked);
    }

    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 6) {
            return str_repeat('*', $len);
        }

        return substr($phone, 0, 4).str_repeat('*', $len - 7).substr($phone, -3);
    }
}
