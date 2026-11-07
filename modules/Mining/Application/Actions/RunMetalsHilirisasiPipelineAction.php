<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Application\Sagas\MetalsHilirisasiLifecycleSaga;
use Modules\Mining\Domain\Models\MiningMetalsTradingContract;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;

final class RunMetalsHilirisasiPipelineAction
{
    public function __construct(
        private readonly MetalsHilirisasiLifecycleSaga $saga
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     success: bool,
     *     run_code: string,
     *     metal_output_tons: float,
     *     receipt_number: ?string,
     *     audit_trail: list<string>,
     *     ledger_journals: list<string>,
     *     error_message: ?string
     * }
     */
    public function execute(
        MiningSmelterFurnaceRun $run,
        ?MiningMetalsTradingContract $contract = null,
        array $options = []
    ): array {
        return $this->saga->executeSmelterHilirisasiPipeline($run, $contract, $options);
    }
}
