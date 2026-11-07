# Spesifikasi Teknis Integrasi Lintas Modul: Kontrak G7-K16, G7-K18 & G7-K19 (Fase 90)

**Domain:** Venue & Entertainment (`modules/Venue/`, sub-item 90.2, 90.3, 90.5)  
**Modul Mitra:** Hotel (`modules/Hotel/`), Human Capital Management (`modules/Hcm/`), Trade Finance (`modules/Trade/`)  
**Tujuan Dokumen:** Menetapkan arsitektur kontrak aditif antarmuka (*additive cross-module contracts*), struktur DTO, semantik transaksi, dan tata kelola kegagalan terdistribusi untuk interoperabilitas tanpa *tight-coupling* antar-modul.  
**Klasifikasi:** Standar Desain Arsitektur Sistem  
**Versi:** 1.0.0-PROD  

---

## 1. Ikhtisar Kontrak Aditif Gelombang 7 (Lini 15 Venue)

Dalam arsitektur modular monolith SuperWeb, modul `Venue` beroperasi sebagai orkestrator pengalaman hiburan malam terpadu. Untuk memenuhi kebutuhan festival skala besar dan operasi beach club premium, Fase 90 memperkenalkan 3 kontrak aditif resmi yang ditempatkan pada modul pemilik layanan masing-masing:

| Kode Kontrak | Interface Kontrak | Modul Penyedia | Modul Konsumen | Tujuan Bisnis |
|---|---|---|---|---|
| **G7-K16** | `RoomBlockReserver` | `Hotel` | `Venue` | Pemesanan dan penguncian blok kamar hotel/glamping untuk paket festival multi-hari |
| **G7-K18** | `ShiftBountyPublisher` | `Hcm` | `Venue` | Penerbitan insentif kilat (*surge bounty*) staf bar saat lonjakan pengunjung mendadak |
| **G7-K19** | `ImportLandedCostQuoter` | `Trade` | `Venue` | Kalkulasi landed cost komprehensif spirit impor sesuai PMK 160/2023 |

---

## 2. Kontrak G7-K16: `RoomBlockReserver` (Modul Hotel)

### 2.1 Lokasi & Deklarasi Interface
- **Namespace Interface:** `Modules\Hotel\Contracts\RoomBlockReserver`
- **Implementasi Layanan:** `Modules\Hotel\Application\Services\RoomBlockReserverService`

### 2.2 Metode & Tanda Tangan
```php
namespace Modules\Hotel\Contracts;

interface RoomBlockReserver
{
    /**
     * Memesan dan mengunci alokasi blok kamar hotel untuk komponen festival bundle.
     *
     * @param string $blockReference Kode referensi pemesanan blok kamar
     * @param string $roomType Tipe kamar (mis. DELUXE_OCEAN, VIP_VILLA, GLAMPING_SUITE)
     * @param int $totalRooms Jumlah kamar yang dipesan
     * @param int $nightCount Durasi menginap (jumlah malam)
     * @param int $ratePerNightIdr Tarif per kamar per malam dalam integer Rupiah
     * @return array{
     *     success: bool,
     *     reservation_code: string,
     *     total_charge_idr: int,
     *     held_rooms: int,
     *     status: string,
     *     message: string
     * }
     */
    public function reserveRoomBlock(
        string $blockReference,
        string $roomType,
        int $totalRooms,
        int $nightCount,
        int $ratePerNightIdr
    ): array;
}
```

### 2.3 Aturan Bisnis & Penanganan Kegagalan
1. **Verifikasi Ketersediaan Inventaris:** Hotel memeriksa ketersediaan fisik kamar pada rentang tanggal festival. Jika kamar tersisa $< \text{totalRooms}$, reservasi ditolak seketika (*fail-closed*) dengan pesan `INSUFFICIENT_ROOM_INVENTORY`.
2. **Penguncian Escrow Folio:** Nilai total biaya $\text{TotalCharge} = \text{totalRooms} \times \text{nightCount} \times \text{ratePerNightIdr}$ dicatat sebagai piutang blok kamar (`hotel:room_block_receivable:IDR`) dan diimbangi dengan pendapatan sewa diterima di muka (`hotel:unearned_room_revenue:IDR`).
3. **Kompensasi Pembatalan:** Jika festival dibatalkan akibat cuaca buruk atau pencabutan izin kepolisian, `RoomBlockReserverService` menyediakan metode pelepasan blokir tanpa penalti ganti rugi kamar (*force majeure release*).

---

## 3. Kontrak G7-K18: `ShiftBountyPublisher` (Modul HCM)

### 3.1 Lokasi & Deklarasi Interface
- **Namespace Interface:** `Modules\Hcm\Contracts\ShiftBountyPublisher`
- **Implementasi Layanan:** `Modules\Hcm\Application\Services\ShiftBountyPublisherService`

### 3.2 Metode & Tanda Tangan
```php
namespace Modules\Hcm\Contracts;

interface ShiftBountyPublisher
{
    /**
     * Menerbitkan pengumuman shift kerja kilat dengan insentif bounty darurat.
     *
     * @param string $bountyReference Referensi unik publikasi bounty shift
     * @param string $roleCode Kode peran kerja (mis. BARTENDER, BARBACK, SECURITY_DOOR)
     * @param int $requiredStaff Jumlah staf darurat yang dibutuhkan
     * @param int $baseHourlyRateIdr Upah pokok per jam dalam integer Rupiah
     * @param int $surgeMultiplierBps Pengali insentif darurat dalam basis poin (mis. 15000 bps = 1.5x)
     * @return array{
     *     success: bool,
     *     bounty_id: string,
     *     published_slots: int,
     *     effective_hourly_rate_idr: int,
     *     status: string,
     *     broadcast_channel: string
     * }
     */
    public function publishSurgeBounty(
        string $bountyReference,
        string $roleCode,
        int $requiredStaff,
        int $baseHourlyRateIdr,
        int $surgeMultiplierBps
    ): array;
}
```

### 3.3 Formula Perhitungan Upah Efektif & Alokasi Anggaran
1. **Kalkulasi Upah Efektif:**
   $$\text{Rate}_{\text{effective}} = \left\lfloor \frac{\text{baseHourlyRateIdr} \times \text{surgeMultiplierBps}}{10.000} \right\rfloor$$
2. **Plafon Pengali Maksimum:** Nilai `surgeMultiplierBps` dibatasi pada batas maksimum $25.000\text{ bps}$ ($2,5x$) untuk mencegah pembengkakan liar biaya tenaga kerja langsung.
3. **Penyaluran Notifikasi Kilat:** Pengumuman disiarkan via push notification WebSocket ke seluruh tenaga kerja lepas terdaftar (*internal talent pool* HCM) dengan waktu respons konfirmasi maksimal 15 menit.

---

## 4. Kontrak G7-K19: `ImportLandedCostQuoter` (Modul Trade)

### 4.1 Lokasi & Deklarasi Interface
- **Namespace Interface:** `Modules\Trade\Contracts\ImportLandedCostQuoter`
- **Implementasi Layanan:** `Modules\Trade\Application\Services\ImportLandedCostQuoterService`

### 4.2 Metode & Tanda Tangan
```php
namespace Modules\Trade\Contracts;

interface ImportLandedCostQuoter
{
    /**
     * Menghitung rincian bea masuk, cukai spesifik MMEA, PPh 22, dan PPN impor spirit.
     *
     * @param string $hsCode Kode HS kepabeanan (mis. 2208.30.00 untuk Whiskies)
     * @param string $productName Nama spirit impor
     * @param int $bottleCount Jumlah botol spirit
     * @param int $bottleVolumeMl Volume per botol dalam mililiter
     * @param float $abv Kadar alkohol dalam persentase volume (mis. 40.0%)
     * @param int $cifPriceUsdCents Nilai CIF dalam satuan sen USD per botol
     * @param int $exchangeRateIdr Kurs pajak konversi Rupiah per 1 USD
     * @return array{
     *     cif_idr: int,
     *     import_duty_idr: int,
     *     cukai_mmea_idr: int,
     *     import_dpp_idr: int,
     *     pph_22_idr: int,
     *     ppn_import_idr: int,
     *     total_landed_cost_idr: int,
     *     cost_per_bottle_idr: int,
     *     mmea_group: string,
     *     cukai_rate_per_liter: int
     * }
     */
    public function quoteSpiritsLandedCost(
        string $hsCode,
        string $productName,
        int $bottleCount,
        int $bottleVolumeMl,
        float $abv,
        int $cifPriceUsdCents,
        int $exchangeRateIdr
    ): array;
}
```

### 4.3 Logika Penentuan Golongan & Cukai Spesifik PMK 160/2023
- **ABV $\le 5\%$:** Golongan A, Cukai Rp 16.500 per liter.
- **$5\% < \text{ABV} \le 20\%$:** Golongan B, Cukai Rp 53.000 per liter (impor).
- **$20\% < \text{ABV} \le 55\%$:** Golongan C, Cukai Rp 148.000 per liter (impor).
- **Bea Masuk:** $150\%$ dari nilai pabean CIF IDR untuk seluruh spirit sulingan beralkohol tinggi.
- **Pajak Impor:** PPh Pasal 22 sebesar $2,5\%$ dan PPN Impor sebesar $11\%$ dari Dasar Pengenaan Pajak Nilai Impor.

---

## 5. Protokol Verifikasi Dua Sumber (*Two-Source Audit*)

Untuk memastikan tidak ada ketidakcocokan saldo antar-modul (*inter-module reconciliation mismatch*):
1. **Audit Kamar Festival:** Jumlah kamar yang dialokasikan pada tabel `ven_festival_bundle_allocations` wajib sama dengan jumlah kamar yang terblokir pada `Modules\Hotel\Domain\Models\HotelRoomBlock`.
2. **Audit Shift Darurat:** Total pembayaran bounty pada penggajian shift bar wajib sesuai dengan rekaman publikasi pada `Modules\Hcm\Domain\Models\HcmShiftBounty`.
3. **Audit Cukai & Inventaris:** Nilai persediaan spirit pada `ven_bar_inventory_items` wajib sama dengan total landed cost yang dihitung oleh `ImportLandedCostQuoter`.

Dokumen ini menjadi spesifikasi teknis definitif integrasi sistem lintas pilar SuperWeb.

---

## 6. Service Level Agreement (SLA) & Metrik Keberhasilan Pemrosesan

Untuk menjaga kinerja real-time pada sistem pemesanan festival dan operasi lantai klub malam:
1. **Latensi Reservasi Blok Kamar (G7-K16):** $\le 120\text{ ms}$ pada persentil $p95$ dan $\le 250\text{ ms}$ pada $p99$.
2. **Latensi Siaran Bounty Kilat (G7-K18):** Notifikasi disiarkan ke antrean worker dalam $\le 50\text{ ms}$ sejak pemicu lonjakan terdeteksi.
3. **Akurasi Landed Cost Impor (G7-K19):** Deviasi perhitungan pajak dan cukai terhadap penetapan resmi Bea Cukai (CEISA) wajib $0\text{ IDR}$ ($100\%$ deterministik).
4. **Resiliensi Kegagalan:** Setiap pemanggilan kontrak aditif dilindungi oleh mekanisme *idempotency key* berbasis hash SHA-256 untuk mencegah duplikasi pemesanan atau multi-posting akrual.

