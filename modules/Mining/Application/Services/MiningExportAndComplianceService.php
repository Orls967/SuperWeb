<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Mining\Domain\Models\CargoQualityDispute;
use Modules\Mining\Domain\Models\CircularFlyAshSale;
use Modules\Mining\Domain\Models\ExportTerminalStockpile;
use Modules\Mining\Domain\Models\VesselVoyage;
use RuntimeException;

class MiningExportAndComplianceService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function createStockpile(string $terminalCode, string $stockpileCode, string $commodity, float $initialTonnage): ExportTerminalStockpile
    {
        return ExportTerminalStockpile::create([
            'id' => (string) Str::uuid(),
            'terminal_code' => $terminalCode,
            'stockpile_code' => $stockpileCode,
            'commodity' => $commodity,
            'opening_tonnage' => $initialTonnage,
            'loaded_tonnage' => 0.0,
            'remaining_tonnage' => $initialTonnage,
        ]);
    }

    public function loadVesselFromStockpile(string $stockpileId, array $voyageParams): array
    {
        // Sanctions screening
        $blockedCountries = ['IRAN', 'NORTH_KOREA', 'SYRIA'];
        $destCountry = strtoupper($voyageParams['destination_country']);
        if (in_array($destCountry, $blockedCountries, true)) {
            throw new RuntimeException("Export blocked: destination country {$destCountry} is subject to active trade sanctions.");
        }

        $stockpile = ExportTerminalStockpile::findOrFail($stockpileId);
        $loadTonnage = (float) $voyageParams['loaded_tonnage'];

        if ($loadTonnage > $stockpile->remaining_tonnage) {
            throw new RuntimeException("Stockpile volume shortfall: requested {$loadTonnage}t exceeds available stockpile {$stockpile->remaining_tonnage}t.");
        }

        return DB::transaction(function () use ($stockpile, $voyageParams, $loadTonnage, $destCountry) {
            $stockpile->loaded_tonnage += $loadTonnage;
            $stockpile->remaining_tonnage -= $loadTonnage;
            $stockpile->save();

            $voyageNumber = $voyageParams['voyage_number'] ?? 'VOY-'.strtoupper(Str::random(8));
            $bolHash = hash('sha256', "BOL:{$voyageNumber}:{$voyageParams['vessel_name']}:{$loadTonnage}");

            $voyage = VesselVoyage::create([
                'id' => (string) Str::uuid(),
                'voyage_number' => $voyageNumber,
                'vessel_name' => $voyageParams['vessel_name'],
                'buyer_party_id' => $voyageParams['buyer_party_id'],
                'destination_country' => $destCountry,
                'contracted_tonnage' => (float) ($voyageParams['contracted_tonnage'] ?? $loadTonnage),
                'loaded_tonnage' => $loadTonnage,
                'bill_of_lading_hash' => $bolHash,
                'allowed_laytime_hours' => (float) ($voyageParams['allowed_laytime_hours'] ?? 72.0),
                'actual_laytime_hours' => 0.0,
                'demurrage_rate_per_hour_minor' => (int) ($voyageParams['demurrage_rate_per_hour_minor'] ?? 5000000), // e.g. 5M IDR/hr
                'demurrage_total_minor' => 0,
                'sanctions_blocked' => false,
                'status' => 'LOADING',
            ]);

            return [
                'stockpile' => $stockpile,
                'voyage' => $voyage,
            ];
        });
    }

    public function finalizeLaytimeAndDemurrage(string $voyageId, float $actualHours): VesselVoyage
    {
        $voyage = VesselVoyage::findOrFail($voyageId);
        $voyage->actual_laytime_hours = $actualHours;

        $excessHours = max(0.0, $actualHours - $voyage->allowed_laytime_hours);
        $demurrageTotal = (int) round($excessHours * $voyage->demurrage_rate_per_hour_minor);

        $voyage->demurrage_total_minor = $demurrageTotal;
        $voyage->status = 'DEPARTED';
        $voyage->save();

        if ($demurrageTotal > 0) {
            $this->ledgerService->post(new PostingDTO(
                type: 'MINING_VESSEL_DEMURRAGE_CLAIM',
                description: "Vessel demurrage claim for voyage {$voyage->voyage_number}",
                idempotencyKey: 'MIN-DEM-'.$voyage->voyage_number,
                entries: [
                    PostingEntryDTO::forCode('min:demurrage_receivable:IDR', 'IDR', $demurrageTotal),
                    PostingEntryDTO::forCode('min:demurrage_revenue:IDR', 'IDR', -$demurrageTotal),
                ],
                referenceType: 'VESSEL_VOYAGE',
                referenceId: $voyage->voyage_number,
            ));
        }

        return $voyage;
    }

    public function reportCargoAssayDispute(array $params): CargoQualityDispute
    {
        $sellerGrade = (float) $params['seller_grade_pct'];
        $buyerGrade = (float) $params['buyer_grade_pct'];
        $tolerance = (float) ($params['grade_tolerance_pct'] ?? 0.20);

        $variance = abs($sellerGrade - $buyerGrade);
        $exceedsTolerance = $variance > $tolerance;

        $sealedSample = $exceedsTolerance ? 'SAMPLE-SEAL-'.strtoupper(Str::random(8)) : null;

        return CargoQualityDispute::create([
            'id' => (string) Str::uuid(),
            'dispute_number' => 'DISP-'.strtoupper(Str::random(8)),
            'voyage_id' => $params['voyage_id'],
            'surveyor_party_id' => $params['surveyor_party_id'],
            'seller_grade_pct' => $sellerGrade,
            'buyer_grade_pct' => $buyerGrade,
            'grade_tolerance_pct' => $tolerance,
            'sealed_sample_code' => $sealedSample,
            'is_payment_held' => $exceedsTolerance,
            'status' => $exceedsTolerance ? 'HELD' : 'RESOLVED',
        ]);
    }

    public function recordCircularFlyAshSale(array $params): CircularFlyAshSale
    {
        $tonnage = (float) $params['fly_ash_tonnage'];
        $pricePerTon = (int) $params['price_per_ton_minor'];
        $totalRevenue = (int) round($tonnage * $pricePerTon);

        $orderNumber = 'ASH-'.strtoupper(Str::random(8));

        $tx = $this->ledgerService->post(new PostingDTO(
            type: 'MINING_CIRCULAR_FLY_ASH_SALES',
            description: "Circular fly ash byproduct sale to cement plant {$params['cement_buyer_party_id']}",
            idempotencyKey: 'MIN-ASH-'.$orderNumber,
            entries: [
                PostingEntryDTO::forCode('min:trade_receivable:IDR', 'IDR', $totalRevenue),
                PostingEntryDTO::forCode('min:circular_economy_revenue:IDR', 'IDR', -$totalRevenue),
            ],
            referenceType: 'CIRCULAR_FLY_ASH_SALE',
            referenceId: $orderNumber,
        ));

        return CircularFlyAshSale::create([
            'id' => (string) Str::uuid(),
            'order_number' => $orderNumber,
            'cement_buyer_party_id' => $params['cement_buyer_party_id'],
            'fly_ash_tonnage' => $tonnage,
            'price_per_ton_minor' => $pricePerTon,
            'total_revenue_minor' => $totalRevenue,
            'ledger_transaction_id' => $tx->id,
        ]);
    }
}
