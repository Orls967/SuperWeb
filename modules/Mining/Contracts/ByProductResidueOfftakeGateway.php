<?php

declare(strict_types=1);

namespace Modules\Mining\Contracts;

/**
 * Kontrak G7-K17: By-Product Residue & Circular Economy Gateway.
 * Menghubungkan pelacakan produk sampingan smelter (terak nikel / slag, asam sulfat, gypsum)
 * dengan manifest lingkungan SIMPEL KLHK dan offtaker sirkular industri semen / agregat jalan.
 */
interface ByProductResidueOfftakeGateway
{
    /**
     * Mengirimkan manifest digital pengangkutan limbah non-B3 terdaftar (terak nikel / slag).
     *
     * @param  array<string, mixed>  $manifestDetails
     * @return array{manifest_number: string, manifest_status: string, klhk_approval_code: string}
     */
    public function transmitEnvironmentalManifest(string $manifestNumber, string $byproductCode, float $tons, string $carrierCode, array $manifestDetails = []): array;

    /**
     * Memverifikasi izin kelayakan lingkungan hidup & pemanfaatan material offtaker.
     */
    public function verifyOfftakerRecyclingPermit(string $buyerCode, string $wasteType): bool;
}
