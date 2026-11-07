<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\DestinationPackage;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocationInput;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocationResult;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocator;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspVerifier;
use RuntimeException;

/**
 * Tindakan Pembelian Paket Destinasi Multi-Vendor dengan Alokasi Nilai Wajar PSAK 72
 * dan Penahanan Dana Escrow Terpusat.
 */
final class PurchaseDestinationPackageAction
{
    public function __construct(
        private readonly ?LedgerService $ledger,
        private readonly BundleSspAllocator $allocator,
        private readonly BundleSspVerifier $verifier,
    ) {}

    /**
     * @param  array<int, array{name: string, ssp_idr: int, vendor_id: string}>  $components
     * @return array{package_code: string, bundle_price_idr: int, allocation_result: BundleSspAllocationResult}
     */
    public function execute(
        string $packageId,
        string $guestId,
        int $packagePriceIdr,
        array $components,
        string $idempotencyKey
    ): array {
        if ($packagePriceIdr <= 0) {
            throw new InvalidArgumentException('Harga paket liburan harus lebih besar dari 0.');
        }

        $package = DestinationPackage::findOrFail($packageId);

        // Eksekusi Algoritma BundleSspAllocator (PSAK 72 / IFRS 15)
        $input = new BundleSspAllocationInput(
            bundlePriceIdr: $packagePriceIdr,
            platformFeeBps: 500, // 5% Platform fee
            components: $components
        );

        $result = $this->allocator->allocate($input);

        if (! $this->verifier->verify($result->certificate)) {
            throw new RuntimeException("Verifikasi sertifikat BundleSSP gagal untuk paket {$package->package_code}");
        }

        // Posting Pembayaran Tamu ke Akun Penampungan Escrow
        if ($this->ledger) {
            $this->ledger->post(new PostingDTO(
                type: 'HTL_DESTINATION_PKG_SETTLE',
                description: "Initial escrow deposit for destination package {$package->package_code} guest {$guestId}",
                idempotencyKey: "htl:pkg_purchase:{$package->id}:{$guestId}:{$idempotencyKey}",
                entries: Posting::lines()
                    ->debit('clearing:external:IDR', $packagePriceIdr)
                    ->credit('escrow:destination_package:IDR', $packagePriceIdr)
                    ->toEntries('IDR'),
                referenceType: 'htl_destination_packages',
                referenceId: $package->id,
            ));
        }

        return [
            'package_code' => $package->package_code,
            'bundle_price_idr' => $packagePriceIdr,
            'allocation_result' => $result,
        ];
    }
}
