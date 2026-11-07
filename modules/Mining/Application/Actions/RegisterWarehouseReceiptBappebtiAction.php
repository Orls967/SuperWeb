<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Contracts\WarehouseReceiptRegistryGateway;
use Modules\Mining\Domain\Enums\WarehouseReceiptStatus;
use Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt;
use Modules\Mining\Domain\StateMachines\WarehouseReceiptCollateralStateMachine;

final class RegisterWarehouseReceiptBappebtiAction
{
    public function __construct(
        private readonly WarehouseReceiptCollateralStateMachine $stateMachine,
        private readonly WarehouseReceiptRegistryGateway $registryGateway
    ) {}

    public function execute(MiningMetalsWarehouseReceipt $receipt): MiningMetalsWarehouseReceipt
    {
        // 1. Verifikasi kelayakan assay lab
        $this->stateMachine->transition($receipt, WarehouseReceiptStatus::VERIFIED_ASSAY);

        // 2. Kirim payload registrasi ke gateway Bappebti ISWARE
        $res = $this->registryGateway->registerWarehouseReceipt($receipt->receipt_number, [
            'tons' => $receipt->certified_weight_metric_tons,
            'assay_id' => $receipt->assay_id,
            'warehouse' => $receipt->warehouse_code,
        ]);

        $receipt->metadata = array_merge($receipt->metadata ?? [], [
            'bappebti_registration_number' => $res['registration_number'],
            'bappebti_signature' => $res['digital_signature'],
        ]);
        $receipt->save();

        // 3. Transisi ke REGISTERED_BAPPEBTI
        $this->stateMachine->transition($receipt, WarehouseReceiptStatus::REGISTERED_BAPPEBTI);

        return $receipt;
    }
}
