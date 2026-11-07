<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Modules\Mining\Contracts\WarehouseReceiptRegistryGateway;

final class WarehouseReceiptRegistryGatewayService implements WarehouseReceiptRegistryGateway
{
    /**
     * @param  array<string, mixed>  $receiptPayload
     * @return array{registration_number: string, bappebti_status: string, digital_signature: string}
     */
    public function registerWarehouseReceipt(string $receiptNumber, array $receiptPayload): array
    {
        $regNumber = 'BAPP-SRG-'.substr(hash('sha256', $receiptNumber.json_encode($receiptPayload)), 0, 16);
        $signature = hash('sha256', "ISWARE:{$receiptNumber}:{$regNumber}");

        return [
            'registration_number' => $regNumber,
            'bappebti_status' => 'REGISTERED_ACTIVE',
            'digital_signature' => $signature,
        ];
    }

    public function verifyReceiptCollateralLien(string $receiptNumber, string $bankName): bool
    {
        return ! empty($receiptNumber) && ! empty($bankName);
    }

    public function releaseReceiptCollateralLien(string $receiptNumber, string $bankName): bool
    {
        return ! empty($receiptNumber) && ! empty($bankName);
    }
}
