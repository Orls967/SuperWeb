<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Logistics\Application\Actions\BookPostpaidShipmentAction;
use Modules\Logistics\Application\Actions\QuoteShipmentAction;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Package;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipperAccount;
use Modules\Logistics\Domain\Services\ChargeableWeightCalculator;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;
use Throwable;

class ProcessBulkShipmentUploadJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly User $shipper,
        public readonly string $filePath,
        public readonly string $batchId
    ) {}

    public function uniqueId(): string
    {
        return $this->batchId;
    }

    public function handle(
        QuoteShipmentAction $quoteAction,
        BookPostpaidShipmentAction $bookPostpaidAction,
        ChargeableWeightCalculator $calculator
    ): void {
        $realPath = Storage::disk('local')->path($this->filePath);
        if (! file_exists($realPath)) {
            $realPath = $this->filePath;
        }

        if (! file_exists($realPath) || ! is_readable($realPath)) {
            $this->saveSummary(0, 0, 1, [['row' => 0, 'error' => 'File CSV tidak dapat dibaca atau tidak ditemukan.']]);

            return;
        }

        $handle = fopen($realPath, 'r');
        if (! $handle) {
            $this->saveSummary(0, 0, 1, [['row' => 0, 'error' => 'Gagal membuka stream file CSV.']]);

            return;
        }

        // Read header
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            $this->saveSummary(0, 0, 1, [['row' => 0, 'error' => 'File CSV kosong atau header tidak valid.']]);

            return;
        }

        $headerMap = array_flip(array_map('trim', array_map('strtolower', $header)));

        $rowIdx = 1;
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        $shipperAccount = ShipperAccount::where('shipper_id', $this->shipper->id)
            ->where('is_active', true)
            ->first();

        while (($row = fgetcsv($handle)) !== false) {
            $rowIdx++;
            if ($rowIdx > 5001) {
                // Limit strictly to 5,000 data rows
                $errors[] = ['row' => $rowIdx, 'error' => 'Batas maksimal 5.000 baris kargo massal terlampaui. Baris sisa diabaikan.'];
                break;
            }

            if (empty(array_filter($row))) {
                continue; // Skip blank lines
            }

            try {
                $originCode = trim($row[$headerMap['origin_code'] ?? 0] ?? '');
                $destCode = trim($row[$headerMap['destination_code'] ?? 1] ?? '');
                $serviceLevelStr = trim($row[$headerMap['service_level'] ?? 2] ?? 'regular');
                $consigneeName = trim($row[$headerMap['consignee_name'] ?? 3] ?? '');
                $consigneePhone = trim($row[$headerMap['consignee_phone'] ?? 4] ?? '');
                $consigneeAddress = trim($row[$headerMap['consignee_address'] ?? 5] ?? '');
                $weightG = (int) ($row[$headerMap['weight_g'] ?? 6] ?? 1000);
                $lengthMm = (int) ($row[$headerMap['length_mm'] ?? 7] ?? 100);
                $widthMm = (int) ($row[$headerMap['width_mm'] ?? 8] ?? 100);
                $heightMm = (int) ($row[$headerMap['height_mm'] ?? 9] ?? 100);
                $description = trim($row[$headerMap['description'] ?? 10] ?? 'Kargo Massal');

                if (empty($originCode) || empty($destCode) || empty($consigneeName) || empty($consigneePhone)) {
                    throw new \InvalidArgumentException('Data wajib (origin, destination, consignee_name, consignee_phone) tidak lengkap.');
                }

                $originLoc = Location::where('code', $originCode)->first();
                if (! $originLoc) {
                    throw new \InvalidArgumentException("Kode lokasi asal '{$originCode}' tidak ditemukan di jaringan.");
                }

                $destLoc = Location::where('code', $destCode)->first();
                if (! $destLoc) {
                    throw new \InvalidArgumentException("Kode lokasi tujuan '{$destCode}' tidak ditemukan di jaringan.");
                }

                $serviceLevel = ServiceLevel::tryFrom($serviceLevelStr) ?? ServiceLevel::Regular;

                $packageData = [
                    [
                        'weight_g' => max(100, $weightG),
                        'length_mm' => max(10, $lengthMm),
                        'width_mm' => max(10, $widthMm),
                        'height_mm' => max(10, $heightMm),
                        'description' => $description,
                    ],
                ];

                $quote = $quoteAction->execute(
                    shipper: $this->shipper,
                    originLocationId: $originLoc->id,
                    destinationLocationId: $destLoc->id,
                    serviceLevel: $serviceLevel,
                    packages: $packageData
                );

                if ($shipperAccount) {
                    $bookPostpaidAction->execute(
                        shipper: $this->shipper,
                        quote: $quote,
                        consigneeName: $consigneeName,
                        consigneePhone: $consigneePhone,
                        consigneeAddress: ['street' => $consigneeAddress, 'city' => $destLoc->city]
                    );
                } else {
                    // For non-postpaid bulk, save as Draft awaiting payment
                    $trackingNumber = TrackingNumber::generate();
                    $chargeableWeightGrams = (int) round(((float) $quote->chargeable_weight_kg) * 1000);

                    $shipment = Shipment::create([
                        'tracking_number' => $trackingNumber,
                        'shipper_id' => $this->shipper->id,
                        'consignee_name' => $consigneeName,
                        'consignee_phone' => $consigneePhone,
                        'consignee_address' => ['street' => $consigneeAddress, 'city' => $destLoc->city],
                        'origin_location_id' => $originLoc->id,
                        'destination_location_id' => $destLoc->id,
                        'service_level' => $serviceLevel,
                        'mode' => $quote->mode,
                        'payment_terms' => PaymentTerms::Prepaid,
                        'status' => ShipmentStatus::Draft,
                        'total_chargeable_weight_g' => $chargeableWeightGrams,
                        'total_amount_idr' => $quote->total_amount_idr,
                        'quote_id' => $quote->id,
                    ]);

                    Package::create([
                        'shipment_id' => $shipment->id,
                        'weight_g' => $packageData[0]['weight_g'],
                        'length_mm' => $packageData[0]['length_mm'],
                        'width_mm' => $packageData[0]['width_mm'],
                        'height_mm' => $packageData[0]['height_mm'],
                        'description' => $description,
                    ]);

                    $quote->is_booked = true;
                    $quote->save();
                }

                $successCount++;
            } catch (Throwable $e) {
                $errorCount++;
                $errors[] = [
                    'row' => $rowIdx,
                    'error' => $e->getMessage(),
                ];
            }
        }

        fclose($handle);

        $this->saveSummary($rowIdx - 1, $successCount, $errorCount, $errors);
    }

    /**
     * @param  array<int, array{row: int, error: string}>  $errors
     */
    private function saveSummary(int $totalRows, int $successCount, int $errorCount, array $errors): void
    {
        $dir = storage_path('app/logistics/bulk_reports');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $summary = [
            'batch_id' => $this->batchId,
            'shipper_id' => $this->shipper->id,
            'total_rows' => $totalRows,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'has_errors' => ! empty($errors),
            'processed_at' => now()->toIso8601String(),
        ];

        File::put("{$dir}/{$this->batchId}_summary.json", json_encode($summary, JSON_PRETTY_PRINT));

        if (! empty($errors)) {
            $csvOut = fopen("{$dir}/{$this->batchId}_errors.csv", 'w');
            if ($csvOut) {
                fputcsv($csvOut, ['Baris', 'Keterangan Error']);
                foreach ($errors as $err) {
                    fputcsv($csvOut, [$err['row'], $err['error']]);
                }
                fclose($csvOut);
            }
        }
    }
}
