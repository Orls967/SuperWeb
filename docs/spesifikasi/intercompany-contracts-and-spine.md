# Spesifikasi Integrasi Kontrak Modul & Event Spine Intercompany

## 1. Arsitektur Kontrak Lintas Modul (Cross-Module Boundaries)
Berdasarkan aturan arsitektur W8 dan KONSEP §A1-A4:
- Komunikasi antar-modul hanya diizinkan melalui antarmuka kontrak resmi di direktori `Contracts/` atau melalui sistem bus asynchronous (`EventSpineInterface` / `OutboxBusInterface`).
- Dilarang mengimpor model Eloquent antar modul (Aturan A1).
- Dilarang menjalankan query basis data lintas tabel modul lain (Aturan A2).

---

## 2. Kontrak Baru: `SubledgerTotalProvider`
Kontrak ini dibuat di `Modules\Intercompany\Contracts\SubledgerTotalProvider` untuk menyediakan akses pembacaan agregasi saldo subledger intercompany bagi modul Enterprise Finance dan auditor tanpa mengekspos tabel internal `ic_*`.

```php
namespace Modules\Intercompany\Contracts;

interface SubledgerTotalProvider
{
    /**
     * Mendapatkan total saldo piutang intercompany unsettled untuk entitas kreditur.
     */
    public function getTotalReceivableMinor(string $entityCode, string $currency = 'IDR'): int;

    /**
     * Mendapatkan total saldo hutang intercompany unsettled untuk entitas debitur.
     */
    public function getTotalPayableMinor(string $entityCode, string $currency = 'IDR'): int;

    /**
     * Mendapatkan rekapitulasi saldo timbal balik bilateral antar dua entitas.
     *
     * @return array{
     *     seller_entity: string,
     *     buyer_entity: string,
     *     receivable_minor: int,
     *     payable_minor: int,
     *     net_discrepancy_minor: int,
     *     status: string
     * }
     */
    public function getBilateralReciprocalSummary(string $sellerEntity, string $buyerEntity, string $currency = 'IDR'): array;

    /**
     * Mendapatkan total laba persediaan intra-grup yang belum terealisasi pada akhir periode.
     */
    public function getUnrealizedInventoryProfitTotalIdr(string $period): int;
}
```

---

## 3. Integrasi Event Spine & Outbox Bus
Setiap kejadian keuangan intercompany penting menerbitkan event terstandarisasi sesuai format katalog event:
1. `ic.transaction.mirrored.v1`: Diterbitkan ketika transaksi cermin berhasil diposting di kedua belah pihak.
   - Produsen: `Intercompany`
   - Konsumen: `EnterpriseFinance`, `Core`
   - Payload: `transaction_id`, `selling_entity`, `buying_entity`, `amount_idr`, `status`.
2. `ic.loan.disbursed.v1`: Diterbitkan ketika fasilitas pinjaman antar-entitas dicairkan.
   - Produsen: `Intercompany`
   - Konsumen: `Treasury`, `EnterpriseFinance`
   - Payload: `loan_agreement_number`, `lender_entity`, `borrower_entity`, `principal_idr`.
3. `ic.consolidation.eliminated.v1`: Diterbitkan saat jurnal eliminasi konsolidasi periodik selesai dibukukan.
   - Produsen: `Intercompany`
   - Konsumen: `EnterpriseFinance`
   - Payload: `period`, `elimination_code`, `total_eliminated_idr`.

---

## 4. Integrasi Task Inbox & Governance Guardrails
Ketika algoritma mendeteksi anomali:
1. Diskrepansi rekonsiliasi bilateral $\Delta > 0$ yang tidak terselesaikan pada tanggal cut-off.
2. Pelanggaran batas kewajaran transfer pricing PMK 172/2023 tanpa waiver.
3. Selisih kepemilikan saham NCI $\ne 100\%$.

Sistem secara otomatis memanggil `Modules\Core\Application\Actions\TaskInbox\TaskInboxService::createTask()` untuk mendaftarkan tugas peninjauan dengan tingkat prioritas `HIGH` kepada Group Tax Controller dan Treasury Manager.
