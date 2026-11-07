<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;

/**
 * Tindakan Pelepasan Dana Escrow Bertahap per Vendor Paket Destinasi
 * saat Konfirmasi Pemenuhan Layanan Tercapai.
 */
final class ReleaseDestinationPackageMilestoneAction
{
    public function __construct(
        private readonly ?LedgerService $ledger,
    ) {}

    /**
     * @return array{package_id: string, vendor_id: string, released_amount_idr: int, status: string}
     */
    public function execute(
        string $packageId,
        string $vendorType,
        string $vendorId,
        int $milestoneAmountIdr,
        string $milestoneProofRef,
        string $idempotencyKey
    ): array {
        if ($milestoneAmountIdr <= 0) {
            throw new InvalidArgumentException('Jumlah pelepasan milestone harus lebih besar dari 0.');
        }

        $vendorAccount = "vendor:settlement:{$vendorType}:IDR";

        // Pelepasan dana dari escrow ke rekening vendor terkait
        if ($this->ledger) {
            $this->ledger->post(new PostingDTO(
                type: 'HTL_DESTINATION_PKG_SETTLE',
                description: "Escrow milestone release for vendor {$vendorId} proof {$milestoneProofRef}",
                idempotencyKey: "htl:pkg_release:{$packageId}:{$vendorId}:{$idempotencyKey}",
                entries: Posting::lines()
                    ->debit('escrow:destination_package:IDR', $milestoneAmountIdr)
                    ->credit($vendorAccount, $milestoneAmountIdr)
                    ->toEntries('IDR'),
                referenceType: 'htl_destination_packages',
                referenceId: $packageId,
            ));
        }

        return [
            'package_id' => $packageId,
            'vendor_id' => $vendorId,
            'released_amount_idr' => $milestoneAmountIdr,
            'status' => 'RELEASED',
        ];
    }
}
