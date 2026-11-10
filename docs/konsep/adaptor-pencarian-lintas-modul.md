# Panduan Integrasi: Adaptor Pencarian Lintas Modul Enterprise

**Fase:** 194 — Platform: Multi-Module Search Provider Architecture  
**Kontrak Antarmuka:** `Modules\Shared\Contracts\SearchProviderInterface`  
**Registry Terpusat:** `Modules\Shared\Application\SearchRegistry`

---

## 1. Arsitektur Kontrak `SearchProviderInterface`

Setiap modul bisnis yang ingin mengekspos entitasnya ke dalam mesin pencarian global (Command Palette Ctrl+K dan API `/api/global-search`) wajib mengimplementasikan antarmuka `SearchProviderInterface`:

```php
namespace Modules\Shared\Contracts;

interface SearchProviderInterface
{
    /**
     * Nama kode unik modul penyedia (misal: "AutoServe", "Logistics", "Contract").
     */
    public function getModuleIdentifier(): string;

    /**
     * Jalankan pencarian leksikal untuk kueri pengguna terfilter.
     *
     * @param string $query Kata kunci pencarian
     * @param array<string> $userRoles Peran aktif pengguna
     * @param string $tenantId ID penyewa aktif
     * @param int $limit Batas maksimal hasil yang dikembalikan
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, array $userRoles, string $tenantId, int $limit = 10): array;

    /**
     * Ekstrak representasi dokumen indeks lengkap untuk pengindeksan terbalik.
     *
     * @param string|int $entityId ID unik entitas bisnis
     * @return array<string, mixed>|null
     */
    public function extractIndexDocument(string|int $entityId): ?array;
}
```

---

## 2. Struktur Dokumen Indeks yang Diharapkan

Ketika `extractIndexDocument()` dipanggil oleh daemon pengindeks (`search:reindex`), data yang dikembalikan harus mematuhi skema kanonikal berikut:

```php
[
    'entity_type' => 'AUTOSERVE_WORK_ORDER',
    'entity_id' => 'WO-2026-0042',
    'title' => 'Perbaikan Transmisi Honda CR-V (B 1234 XYZ)',
    'content' => 'Pelanggan: Budi Santoso. Keluhan: Perpindahan gigi transmisi kasar. Suku cadang: Oli ATF, Filter transmisi. Montir: Ahmad.',
    'route_url' => '/autoserve/work-orders/WO-2026-0042',
    'allowed_roles' => ['service_advisor', 'workshop_manager', 'admin'],
    'tenant_id' => 'TENANT-AUTO-JKT',
    'metadata' => [
        'status' => 'IN_PROGRESS',
        'plate_number' => 'B 1234 XYZ',
        'customer_name' => 'Budi Santoso',
    ],
]
```

---

## 3. Implementasi Adaptor Resmi 7 Modul Bisnis

Pada Fase 194, setidaknya 7 modul bisnis diintegrasikan secara resmi:

1. **`AutoServeSearchProvider` (Modul Bengkel):**
   - Mengindeks: Work Order perbaikan, pelanggan, dan pelat nomor kendaraan.
   - Hak Akses: `service_advisor`, `mechanic`, `workshop_manager`, `admin`.

2. **`AutoDexSearchProvider` (Modul Showroom Otomotif):**
   - Mengindeks: Nomor rangka (VIN), model mobil, tahun pembuatan, dan inventaris unit display.
   - Hak Akses: `sales_agent`, `showroom_manager`, `admin`.

3. **`LogisticsSearchProvider` (Modul Rantai Pasok):**
   - Mengindeks: Nomor resi pengiriman, kota asal, kota tujuan, dan pelat armada logistik.
   - Hak Akses: `fleet_dispatcher`, `logistics_manager`, `driver`, `admin`.

4. **`ContractSearchProvider` (Modul Hukum & Kontrak):**
   - Mengindeks: Judul kontrak legal, nama pihak ketiga, nomor dokumen, dan ringkasan klausa.
   - Hak Akses: `legal_counsel`, `procurement_manager`, `admin`.

5. **`AssetSearchProvider` (Modul Manajemen Aset):**
   - Mengindeks: Tag inventaris barcode aset, lokasi gedung, kategori peralatan pabrik.
   - Hak Akses: `asset_manager`, `facility_engineer`, `admin`.

6. **`MarineSearchProvider` (Modul Transportasi Laut):**
   - Mengindeks: Nomor IMO kapal, manifes kargo pelabuhan, jadwal kedatangan dermaga.
   - Hak Akses: `marine_officer`, `port_authority`, `admin`.

7. **`PartySearchProvider` (Modul Relasi Pihak Ketiga):**
   - Mengindeks: Nama vendor, direktori suplier bahan baku, nomor NPWP korporasi.
   - Hak Akses: `vendor_manager`, `finance_controller`, `admin`.

---

## 4. Mekanisme Pendaftaran ke `SearchRegistry`

Setiap penyedia didaftarkan di dalam Provider modul masing-masing atau di `SharedServiceProvider`:
```php
public function boot(SearchRegistry $registry): void
{
    $registry->registerProvider(app(AutoServeSearchProvider::class));
    $registry->registerProvider(app(LogisticsSearchProvider::class));
    $registry->registerProvider(app(ContractSearchProvider::class));
    // ...
}
```
Ketika kueri masuk ke `GlobalSearchQuery`, registry mengeksekusi penelusuran secara terisolasi dan menggabungkan hasil melalui algoritma `PolicyFirstRanker`.
