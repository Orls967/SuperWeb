# Matriks Integrasi Lintas Modul: Hotel MICE & Event Sales Engine (Fase 114)

## 1. Ikhtisar Interkoneksi Domain
Penyelenggaraan acara perhotelan skala besar menyentuh hampir seluruh modul operasional dalam ekosistem enterprise Superweb. Matriks ini mendokumentasikan antarmuka kontrak, arah aliran data, mekanisme idempotensi, dan kebijakan kompensasi transaksi bila terjadi kegagalan sistem.

```
       ┌────────────────┐
       │  Modul Venue   │◄──── G7-K16 RoomBlockReserver (Reservasi kamar paket konser/VIP)
       └───────┬────────┘
               │
               ▼
       ┌────────────────┐      ┌─────────────────┐
       │  Modul HOTEL   │─────►│   Modul Resto   │ (Pemotongan batch stok bahan baku BEO)
       │   (Fase 114)   │      └─────────────────┘
       └───────┬────────┘
               │               ┌─────────────────┐
               ├──────────────►│Modul Procurement│ (Penerbitan PR otomatis saat bahan baku < buffer)
               │               └─────────────────┘
               │
               │               ┌─────────────────┐
               ├──────────────►│ Modul HCM & Pay │ (Alokasi upah tenaga kerja banquet shift kasual)
               │               └─────────────────┘
               │
               ▼
       ┌────────────────┐
       │ General Ledger │ (Pencatatan termin piutang City Ledger & pengakuan pendapatan)
       └────────────────┘
```

---

## 2. Tabel Spesifikasi Integrasi Lintas Modul

| No | Modul Target | Arah Aliran Data | Kontrak / Interface / Event | Protokol Transaksi & Idempotensi | Kebijakan Kompensasi / Rollback |
|---|---|---|---|---|---|
| 1 | **Venue (Fase 90/116)** | Bidirectional (Inbound & Outbound) | `Modules\Hotel\Contracts\RoomBlockReserver` (`reserveBlock`, `releaseBlock`) | Sinkron PHP Method Call; Pessimistic Row Lock pada kuota kamar blok; Idempotency key unik berbasis kombinasi properti dan kode reservasi | Mengembalikan status kamar ke kuota blok; melepaskan lock inventaris; membatalkan draf folio tamu. |
| 2 | **Resto (Dapur & Bar)** | Outbound (Hotel -> Resto) | `RestoKitchenStockDeductionInterface` / `BatchRequisitionAction` | Database Transaction; Idempotency key format `HTL-BEO-STOCK-{beo_id}-{recipe_hash}` | Pengembalian saldo stok fisik ke gudang pendingin bila BEO dibatalkan sebelum proses masak dimulai. |
| 3 | **Procurement & Inventory** | Outbound (Hotel -> Procurement) | `ProcurementRequisitionPublisher` | Asinkron Event Dispatch; Idempotency key format `HTL-PO-REQ-{beo_id}-{item_id}` | Membatalkan draf Purchase Requisition jika menu diubah oleh klien pada sesi food tasting. |
| 4 | **HCM & Payroll** | Outbound (Hotel -> HCM) | `HcmShiftBountyPublisher` (G7-K18) / `CasualBanquetLaborAllocator` | Event Dispatch; mencatat kebutuhan shift pramusaji dan juru masak tambahan berdasarkan rasio pax BEO | Pembatalan pemanggilan staf kasual paling lambat H-24 jam sebelum shift dimulai tanpa pinalti upah. |
| 5 | **General Ledger & AR** | Outbound (Hotel -> Accounting) | `HotelMiceLedgerReconciliationService` & Posting Engine | Sinkron Atomic Transaction; pembukuan ganda seimbang (Debit Kas/AR = Kredit Pendapatan Sewa Hall & F&B) | Pembuatan jurnal pembalik kompensasi (*Credit Memo / Reversal Voucher*) bila terjadi sengketa atau diskon korektif. |
| 6 | **Exhibition Expo (Fase 78)** | Bidirectional | `ExhibitionBoothReservationInterface` | Sinkron API; pemetaan denah booth pameran, penarikan kabel utilitas listrik kVA, dan logging footfall sensor | Pembatalan reservasi booth membebaskan zona stan pada denah hall dan menghapus alokasi sub-panel daya. |

---

## 3. Rincian Kontrak K16 (RoomBlockReserver)
Modul Hotel Fase 114 tetap mempertahankan kompatibilitas 100% terhadap kontrak `RoomBlockReserver`:
```php
namespace Modules\Hotel\Contracts;

interface RoomBlockReserver
{
    public function reserveBlock(
        string $propertyId,
        string $roomId,
        string $guestUserId,
        string $checkInDate,
        string $checkOutDate,
        int $dailyRateIdr
    ): array;

    public function releaseBlock(string $reservationId, string $reason): bool;
}
```
Pada implementasi Fase 114, pemanggilan `reserveBlock` tidak hanya mengunci reservasi kamar individual, tetapi juga memperbarui statistik `confirmed_pickup_rooms` pada entitas `HtlRoomBlockAllotment` yang relevan, sehingga perhitungan sisa pelepasan bertingkat H-30/H-14/H-7 selalu mencerminkan data aktual secara akurat.
