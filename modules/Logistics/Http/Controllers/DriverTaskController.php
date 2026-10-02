<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Logistics\Application\Actions\CompleteDeliveryAction;
use Modules\Logistics\Application\Actions\ReportFailedDeliveryAction;
use Modules\Logistics\Application\Actions\ScanPickupAction;
use Modules\Logistics\Application\Actions\StartDeliveryAction;
use Modules\Logistics\Domain\Enums\DeliveryFailureReason;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;
use RuntimeException;

class DriverTaskController extends Controller
{
    protected function resolveDriver(Request $request): Driver
    {
        $user = $request->user();

        abort_unless($user && $user->isDriver(), 403, 'Akses ditolak. Halaman khusus pengemudi ekspedisi.');

        $driver = Driver::where('user_id', $user->id)->first();
        abort_unless($driver, 403, 'Akun Anda belum terdaftar sebagai pengemudi.');

        return $driver;
    }

    public function index(Request $request): View
    {
        $driver = $this->resolveDriver($request);

        $stops = Shipment::where('driver_id', $driver->id)
            ->whereIn('status', [ShipmentStatus::Booked->value, ShipmentStatus::AtHub->value, ShipmentStatus::OutForDelivery->value])
            ->with(['origin', 'destination'])
            ->orderBy('booked_at')
            ->get();

        $trips = Schedule::where('driver_id', $driver->id)
            ->whereIn('status', [ScheduleStatus::Scheduled->value, ScheduleStatus::Loading->value, ScheduleStatus::Departed->value])
            ->with(['origin', 'destination', 'asset'])
            ->orderBy('etd')
            ->get();

        return view('logistics::driver.tasks', [
            'driver' => $driver,
            'pickups' => $stops->where('status', ShipmentStatus::Booked)->values(),
            'readyForDelivery' => $stops->where('status', ShipmentStatus::AtHub)->values(),
            'delivering' => $stops->where('status', ShipmentStatus::OutForDelivery)->values(),
            'trips' => $trips,
            'failureReasons' => DeliveryFailureReason::cases(),
        ]);
    }

    public function pickup(Request $request, ScanPickupAction $action): RedirectResponse
    {
        $driver = $this->resolveDriver($request);
        $data = $request->validate(['tracking_number' => 'required|string|max:32']);

        $shipment = Shipment::where('tracking_number', TrackingNumber::normalize($data['tracking_number']))->first();
        if (! $shipment) {
            return back()->with('error', 'Nomor resi tidak terdaftar di sistem.');
        }

        try {
            $action->execute($driver, $shipment);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Pickup resi {$shipment->tracking_number} berhasil dipindai.");
    }

    public function startDelivery(Request $request, int $shipment, StartDeliveryAction $action): RedirectResponse
    {
        $driver = $this->resolveDriver($request);

        try {
            $result = $action->execute($driver, Shipment::findOrFail($shipment));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Pengantaran {$result['shipment']->tracking_number} dimulai. Kode OTP telah dikirim ke pengirim untuk diteruskan ke penerima.");
    }

    public function deliver(Request $request, int $shipment, CompleteDeliveryAction $action): RedirectResponse
    {
        $driver = $this->resolveDriver($request);

        $data = $request->validate([
            'receiver_name' => 'required|string|max:100',
            'otp' => 'required|digits:6',
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:5120',
            'signature' => 'required|string|max:700000',
        ]);

        try {
            $pod = $action->execute(
                $driver,
                Shipment::findOrFail($shipment),
                $data['receiver_name'],
                $data['otp'],
                $request->file('photo'),
                $data['signature'],
                $request->boolean('cod_collected')
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Pengantaran selesai. Bukti serah terima tersimpan (POD #{$pod->id}).");
    }

    public function fail(Request $request, int $shipment, ReportFailedDeliveryAction $action): RedirectResponse
    {
        $driver = $this->resolveDriver($request);

        $data = $request->validate([
            'reason' => ['required', Rule::enum(DeliveryFailureReason::class)],
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $result = $action->execute(
                $driver,
                Shipment::findOrFail($shipment),
                DeliveryFailureReason::from($data['reason']),
                $data['notes'] ?? null
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['returned']) {
            return back()->with('warning', 'Pengantaran gagal 3 kali. Resi otomatis dikembalikan ke pengirim (Return to Sender).');
        }

        return back()->with('success', "Percobaan gagal ke-{$result['attempt']->attempt_number} dicatat. Sisa percobaan: ".(ReportFailedDeliveryAction::MAX_ATTEMPTS - $result['attempt']->attempt_number).'.');
    }
}
