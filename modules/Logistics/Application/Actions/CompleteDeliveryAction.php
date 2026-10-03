<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Events\ShipmentDelivered;
use Modules\Logistics\Domain\Exceptions\CodException;
use Modules\Logistics\Domain\Exceptions\InvalidDeliveryOperationException;
use Modules\Logistics\Domain\Exceptions\InvalidDeliveryOtpException;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\ProofOfDelivery;
use Modules\Logistics\Domain\Models\Shipment;

class CompleteDeliveryAction extends AbstractDriverTaskAction
{
    public const MAX_OTP_ATTEMPTS = 5;

    public const MAX_SIGNATURE_BYTES = 512 * 1024;

    public function __construct(
        protected RecordTrackingEventAction $recordEvent,
        protected RecordCodCollectionAction $recordCod
    ) {}

    /**
     * Selesaikan pengantaran dengan Proof of Delivery: nama penerima, OTP 6 digit, foto bukti
     * dan tanda tangan canvas (data URL PNG).
     */
    public function execute(
        Driver $driver,
        Shipment $shipment,
        string $receiverName,
        string $otp,
        UploadedFile $photo,
        string $signatureDataUrl,
        bool $codCollected = false
    ): ProofOfDelivery {
        $signature = $this->decodeSignature($signatureDataUrl);

        $shipment = Shipment::findOrFail($shipment->id);
        $this->assertAssignedTo($shipment, $driver);

        if ($shipment->cod_amount_idr > 0 && ! $codCollected) {
            throw CodException::notCollected($shipment->tracking_number, $shipment->cod_amount_idr);
        }

        $limiterKey = 'lgx-otp:'.$shipment->id;
        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_OTP_ATTEMPTS)) {
            throw InvalidDeliveryOtpException::locked();
        }

        if ($shipment->status !== ShipmentStatus::OutForDelivery) {
            throw new InvalidDeliveryOperationException("Resi {$shipment->tracking_number} berstatus '{$shipment->status->label()}' dan tidak sedang dalam pengantaran.");
        }

        if (! $shipment->delivery_otp_hash || ! Hash::check($otp, $shipment->delivery_otp_hash)) {
            RateLimiter::hit($limiterKey, 3600);
            throw InvalidDeliveryOtpException::mismatch();
        }

        $directory = 'lgx/pod/'.$shipment->id;
        $photoPath = $photo->storeAs($directory, 'photo-'.Str::uuid().'.'.($photo->guessExtension() ?: 'jpg'), 'local');
        $signaturePath = $directory.'/signature-'.Str::uuid().'.png';
        Storage::disk('local')->put($signaturePath, $signature);

        try {
            $pod = DB::transaction(function () use ($driver, $shipment, $receiverName, $photoPath, $signaturePath) {
                $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

                if ($shipment->status !== ShipmentStatus::OutForDelivery) {
                    throw new InvalidDeliveryOperationException("Resi {$shipment->tracking_number} sudah tidak dalam status pengantaran.");
                }

                $shipment->delivery_otp_hash = null;
                $shipment->transitionTo(ShipmentStatus::Delivered);

                $pod = ProofOfDelivery::create([
                    'shipment_id' => $shipment->id,
                    'driver_id' => $driver->id,
                    'receiver_name' => $receiverName,
                    'otp_verified' => true,
                    'photo_path' => $photoPath,
                    'signature_path' => $signaturePath,
                    'delivered_at' => $shipment->delivered_at,
                ]);

                $this->recordEvent->execute(
                    shipment: $shipment,
                    eventType: 'DELIVERED',
                    locationId: $shipment->destination_location_id,
                    actor: $driver->user,
                    actorRole: 'driver',
                    description: 'Kargo diterima oleh penerima (OTP terverifikasi).',
                    payload: [
                        'driver_id' => $driver->id,
                        'pod_id' => $pod->id,
                        'receiver_name_sha256' => hash('sha256', mb_strtolower(trim($receiverName))),
                        'otp_verified' => true,
                    ]
                );

                if ($shipment->cod_amount_idr > 0) {
                    $this->recordCod->execute($shipment, $driver);
                }

                // Defer until after commit: the freight-revenue listener must not
                // post revenue for a delivery that may still roll back.
                DB::afterCommit(fn () => event(new ShipmentDelivered($shipment)));

                return $pod;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete([$photoPath, $signaturePath]);
            throw $e;
        }

        RateLimiter::clear($limiterKey);

        return $pod;
    }

    protected function decodeSignature(string $dataUrl): string
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw new InvalidDeliveryOperationException('Tanda tangan penerima tidak valid (harus berupa gambar PNG).');
        }

        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);

        if ($binary === false || strlen($binary) < 100 || strlen($binary) > self::MAX_SIGNATURE_BYTES || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            throw new InvalidDeliveryOperationException('Tanda tangan penerima tidak valid atau terlalu besar.');
        }

        return $binary;
    }
}
