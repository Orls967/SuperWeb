<?php

declare(strict_types=1);

namespace Modules\Mining\Contracts;

/**
 * Kontrak G7-K14: Bulk Marine Vessel Logistics Gateway.
 * Menghubungkan sistem logistik tongkang tambang dengan pelabuhan khusus / jetty,
 * data telemetri AIS kapal tunda (tugboat), dan draft survey sensorik.
 */
interface BulkMarineVesselLogisticsGateway
{
    /**
     * Mendapatkan posisi terkini kapal tongkang / tugboat berdasarkan AIS / transponder.
     *
     * @return array{vessel_code: string, latitude: float, longitude: float, speed_knots: float, heading_degrees: float, last_ping_at: string}
     */
    public function getVesselPosition(string $bargeCode): array;

    /**
     * Memvalidasi kelaiklautan tongkang di dermaga pemuatan jetty tambang.
     */
    public function verifyJettyBerthingClearance(string $bargeCode, string $jettyId): bool;

    /**
     * Mengirimkan notifikasi manifest muatan tongkang ke syahbandar / pelabuhan tujuan.
     *
     * @param  array<string, mixed>  $manifestData
     */
    public function notifyPortAuthorityBargeDeparture(string $bargeCode, array $manifestData): bool;

    /**
     * Mengambil tabel hidrostatik kapal tongkang berdasarkan nomor registrasi.
     *
     * @return list<array{draft_m: float, displacement_mt: float, tpc_t_cm: float, lcf_m: float, km_t_m: float}>
     */
    public function fetchVesselHydrostaticTable(string $bargeCode): array;
}
