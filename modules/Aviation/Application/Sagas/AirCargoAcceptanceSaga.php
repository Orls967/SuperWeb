<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Sagas;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Application\Actions\AcceptAirCargoConsignmentAction;
use Modules\Aviation\Domain\Models\AviationCargoConsignment;
use Modules\Aviation\Domain\Models\AviationSagaInstance;

/**
 * Saga Penerimaan Kargo Udara dengan Kompensasi Otomatis (T-4).
 * Alur: VALIDATE_DG -> CHECK_CUSTODY -> ACCEPT_CARGO -> JOURNAL_LEDGER.
 * Kompensasi: Jika gagal inspeksi DG -> release kapasitas & tandai rejected.
 */
class AirCargoAcceptanceSaga
{
    public function __construct(
        private readonly AcceptAirCargoConsignmentAction $acceptAction
    ) {}

    /**
     * @return array{saga_id: int, status: string, consignment: AviationCargoConsignment|null}
     */
    public function run(array $payload, string $correlationId): array
    {
        return DB::transaction(function () use ($payload, $correlationId) {
            $saga = AviationSagaInstance::firstOrCreate(
                ['correlation_id' => $correlationId],
                [
                    'saga_type' => 'AIR_CARGO_ACCEPTANCE',
                    'current_step' => 'STARTED',
                    'status' => 'started',
                    'payload_json' => $payload,
                    'log_json' => [],
                ]
            );

            try {
                $payload['idempotency_key'] = $correlationId;
                $consignment = $this->acceptAction->execute($payload);

                $saga->current_step = 'COMPLETED';
                $saga->status = 'completed';
                $saga->log_json = array_merge($saga->log_json ?? [], [
                    ['step' => 'COMPLETED', 'awb' => $consignment->consignment_number, 'timestamp' => now()->toIso8601String()],
                ]);
                $saga->save();

                return [
                    'saga_id' => $saga->id,
                    'status' => 'completed',
                    'consignment' => $consignment,
                ];
            } catch (\Throwable $e) {
                // Kompensasi jika terjadi kegagalan
                $saga->current_step = 'COMPENSATED_REJECTED';
                $saga->status = 'compensated';
                $saga->log_json = array_merge($saga->log_json ?? [], [
                    ['step' => 'COMPENSATED', 'error' => $e->getMessage(), 'timestamp' => now()->toIso8601String()],
                ]);
                $saga->save();

                throw $e;
            }
        });
    }
}
