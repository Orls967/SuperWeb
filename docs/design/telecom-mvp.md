# Dokumen Desain Teknis: Lini 14 — Telko & Data Center (Tlx MVP)

## 1. Arsitektur Komponen & Modularitas

```
                       ┌─────────────────────────┐
                       │  modules/Tlx/Contracts  │
                       │    IotDeviceRegistry    │
                       └────────────▲────────────┘
                                    │ implements
┌───────────────────────────────────┼──────────────────────────────────┐
│  modules/Tlx                      │                                  │
│  ┌─────────────────────────────┐  │   ┌───────────────────────────┐  │
│  │   IotDeviceRegistryService  ├──┘   │   TelecomNetworkService   │  │
│  └──────────────┬──────────────┘      └─────────────┬─────────────┘  │
│                 │                                   │                │
│  ┌──────────────▼──────────────┐      ┌─────────────▼─────────────┐  │
│  │       CdrRatingService      │      │    NocCorrelationService  │  │
│  │     (Numerics UsageRater)   │      │ (Numerics TopoSuppressor) │  │
│  └──────────────┬──────────────┘      └─────────────┬─────────────┘  │
│                 │                                   │                │
│  ┌──────────────▼──────────────┐      ┌─────────────▼─────────────┐  │
│  │ ConnectivityBillingService  │      │  ColocationBillingService │  │
│  │  (IntercompanyBillingPort)  │      │     (PUE & Peak Power)    │  │
│  └─────────────────────────────┘      └───────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────┘
```

## 2. Kontrak Antarmuka: `IotDeviceRegistry`

```php
namespace Modules\Tlx\Contracts;

use Carbon\CarbonImmutable;

interface IotDeviceRegistry
{
    /**
     * Mendaftarkan perangkat IoT baru ke registry tunggal.
     */
    public function registerDevice(array $payload): array;

    /**
     * Mengaktifkan profil SIM pada perangkat IoT tertentu.
     */
    public function activateSim(string $deviceCode, string $iccid, ?string $idempotencyKey = null): array;

    /**
     * Menangguhkan perangkat (suspension).
     */
    public function suspendDevice(string $deviceCode, string $reason, ?string $idempotencyKey = null): array;

    /**
     * Memensiunkan perangkat (retirement).
     */
    public function retireDevice(string $deviceCode, ?string $idempotencyKey = null): array;

    /**
     * Mencari perangkat berdasarkan device_code atau ICCID SIM.
     */
    public function findDevice(string $identifier): ?array;

    /**
     * Memvalidasi bahwa perangkat aktif pada timestamp tertentu (dipakai modul lain/patch).
     */
    public function assertActive(string $deviceCode, CarbonImmutable $at): bool;
}
```

## 3. Alur Bisnis Utama

### 3.1 Ingest & Rating CDR
1. Data transfer sesi dicatat dalam CDR mentah append-only.
2. Setiap sesi dirating menggunakan `Modules\Shared\Domain\Numerics\UsageRater`:
   - Pembulatan ke atas per 1 KB (1.024 bytes).
3. Agregat bulanan diakumulasi per entitas:
   - Bandwidth kuota paket bulanan dikurangkan.
   - Kelebihan byte dikonversi ke MB integer (ceil) dan dikenai tarif kelebihan.

### 3.2 Penagihan Konektivitas Bulanan via Intercompany
1. Periode penagihan YYYY-MM.
2. Menghitung hari aktif tiap perangkat dalam bulan tersebut.
3. Pro-rata biaya langganan: `round_half_up(fee * active_days / days_in_month)`.
4. Total tagihan diterbitkan lewat `IntercompanyBillingPort::bill(...)`.
5. Menghasilkan transaksi cermin yang siap dieliminasi pada konsolidasi grup.

### 3.3 Colocation Data Center & PUE
1. Rack fee ditagih per bulan sesuai kontrak.
2. Pembacaan daya fasilitas dan daya IT dicatat per interval jam.
3. Puncak daya terukur (kW) dibandingkan dengan komitmen daya kontrak:
   - Kelebihan daya = `max(0, peak_kw - commit_kw) * excess_rate`.
4. PUE = `total_facility_energy_kwh / it_equipment_energy_kwh`.

### 3.4 Korelasi Alarm NOC via `TopologySuppressor`
1. Topologi elemen jaringan didefinisikan sebagai Directed Acyclic Graph (DAG) dari root router ke site dan link.
2. Ketika beberapa alarm muncul, `TopologySuppressor` mengecek apakah suatu alarm memiliki leluhur berstatus DOWN:
   - Jika memiliki leluhur DOWN, alarm ditandai `is_suppressed = true`.
   - Tiket insiden NOC (`tlx_noc_tickets`) hanya dibuat untuk akar masalah (root cause) yang tidak tersupresi.
3. Menghasilkan sertifikat validasi topologi yang diverifikasi oleh `TopologySuppressorVerifier`.

---

## 4. Skema Database Tambahan

Tabel-tabel baru di Lini 14 dengan prefiks `tlx_`:
- `tlx_cdrs`: tabel fakta CDR append-only.
- `tlx_sim_profiles`: katalog SIM/eSIM dan alokasi ke perangkat.
- `tlx_dc_power_readings`: pembacaan daya fasilitas vs IT equipment append-only.
- `tlx_colocation_invoices`: rincian tagihan colocation (rak + kelebihan daya).
- `tlx_network_elements`: elemen jaringan graf topologi.
- `tlx_noc_alarms`: tabel fakta alarm append-only dengan relasi parent/suppression.
- `tlx_noc_tickets`: tiket perbaikan dengan tracking MTTR dan SLA.
