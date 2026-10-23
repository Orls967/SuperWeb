<?php

declare(strict_types=1);

namespace Modules\Agri\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Agri\Domain\Models\AgriContract;
use Modules\Agri\Domain\Models\AgriFarmer;
use Modules\Agri\Ledger\AgriTransactionTypes;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;

class RegisterPlasmaFarmerContractAction
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * @param array{
     *     farmer_id?: string,
     *     farmer_code?: string,
     *     farmer_group_name?: string,
     *     full_name?: string,
     *     phone_number?: string,
     *     land_area_hectares?: float|int,
     *     commodity: string,
     *     planting_date?: string|Carbon,
     *     expected_harvest_date?: string|Carbon,
     *     target_yield_kg: float|int,
     *     guaranteed_floor_price_idr_per_kg: int,
     *     seed_advance_value_idr?: int,
     *     fertilizer_advance_value_idr?: int,
     *     contract_number?: string
     * } $data
     */
    public function execute(array $data): AgriContract
    {
        return DB::transaction(function () use ($data) {
            $farmerId = $data['farmer_id'] ?? null;

            if (! $farmerId) {
                if (empty($data['full_name']) || empty($data['commodity'])) {
                    throw new InvalidArgumentException('Full name and commodity are required to register a new plasma farmer.');
                }

                $farmer = AgriFarmer::create([
                    'farmer_code' => $data['farmer_code'] ?? 'FRM-'.strtoupper(Str::random(6)),
                    'farmer_group_name' => $data['farmer_group_name'] ?? 'Kelompok Tani Plasma Mandiri',
                    'full_name' => $data['full_name'],
                    'phone_number' => $data['phone_number'] ?? '0812000000',
                    'land_area_hectares' => (float) ($data['land_area_hectares'] ?? 1.5),
                    'primary_commodity' => $data['commodity'],
                    'status' => 'active',
                ]);
                $farmerId = (string) $farmer->id;
            }

            $floorPrice = (int) ($data['guaranteed_floor_price_idr_per_kg'] ?? 0);
            if ($floorPrice <= 0) {
                throw new InvalidArgumentException('Guaranteed floor price must be a positive integer per kg.');
            }

            $targetYield = (float) ($data['target_yield_kg'] ?? 0);
            if ($targetYield <= 0) {
                throw new InvalidArgumentException('Target yield kg must be greater than zero.');
            }

            $seedAdvance = (int) ($data['seed_advance_value_idr'] ?? 0);
            $fertAdvance = (int) ($data['fertilizer_advance_value_idr'] ?? 0);
            $totalAdvance = $seedAdvance + $fertAdvance;

            $plantingDate = isset($data['planting_date']) ? Carbon::parse($data['planting_date'])->toDateString() : now()->toDateString();
            $expectedHarvestDate = isset($data['expected_harvest_date']) ? Carbon::parse($data['expected_harvest_date'])->toDateString() : now()->addMonths(3)->toDateString();

            $contractNumber = $data['contract_number'] ?? 'AGR-CTR-'.strtoupper(Str::random(8));

            $contract = AgriContract::create([
                'contract_number' => $contractNumber,
                'farmer_id' => $farmerId,
                'commodity' => $data['commodity'],
                'planting_date' => $plantingDate,
                'expected_harvest_date' => $expectedHarvestDate,
                'target_yield_kg' => $targetYield,
                'seed_advance_value_idr' => $seedAdvance,
                'fertilizer_advance_value_idr' => $fertAdvance,
                'total_advance_deductible_idr' => $totalAdvance,
                'guaranteed_floor_price_idr_per_kg' => $floorPrice,
                'status' => 'active',
            ]);

            // If input loan advance was disbursed, record in ledger
            if ($totalAdvance > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: AgriTransactionTypes::AGRI_LOAN_DISBURSEMENT->value,
                    description: "Agri input loan disbursement for contract {$contract->contract_number} seed/fert advance",
                    idempotencyKey: "AGRI-ADVANCE-{$contract->contract_number}",
                    entries: Posting::lines()
                        ->debit('agri:farmer_advance_receivable:IDR', $totalAdvance)
                        ->credit('agri:escrow_disbursement:IDR', $totalAdvance)
                        ->toEntries('IDR'),
                    referenceType: 'AGRI_CONTRACT',
                    referenceId: (string) $contract->id,
                ));
            }

            return $contract;
        });
    }
}
