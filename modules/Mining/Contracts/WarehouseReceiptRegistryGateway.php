<?php

declare(strict_types=1);

namespace Modules\Mining\Contracts;

/**
 * Kontrak G7-K16: Warehouse Receipt System (SRG) & Bappebti ISWARE Gateway.
 * Menghubungkan modul pergudangan logam olahan dengan Pusat Registrasi Resi Gudang
 * (PT Kliring Berjangka Indonesia / ISWARE Bappebti) dan bank kreditur SCF.
 */
interface WarehouseReceiptRegistryGateway
{
    /**
     * Mendaftarkan resi gudang ke sistem Pusat Registrasi Bappebti / ISWARE.
     *
     * @param  array<string, mixed>  $receiptPayload
     * @return array{registration_number: string, bappebti_status: string, digital_signature: string}
     */
    public function registerWarehouseReceipt(string $receiptNumber, array $receiptPayload): array;

    /**
     * Mengunci dan mencatat hak jaminan fidusia / agunan SCF atas resi gudang untuk bank mitra.
     */
    public function verifyReceiptCollateralLien(string $receiptNumber, string $bankName): bool;

    /**
     * Menerbitkan pelepasan hak jaminan fidusia pasca-pelunasan fasilitas pembiayaan SCF.
     */
    public function releaseReceiptCollateralLien(string $receiptNumber, string $bankName): bool;
}
