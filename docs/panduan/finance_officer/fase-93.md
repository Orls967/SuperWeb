# Panduan Operasional Peran: Finance Officer (`finance_officer`) — Fase 93

## 1. Tanggung Jawab Utama
Finance Officer bertanggung jawab atas tata kelola keuangan penambangan, verifikasi tagihan kontraktor angkut (hauling), penahanan dana (hold) atas anomali BBM yang terdeteksi oleh sistem, dan resolusi pemotongan atau pelepasan dana hold.

## 2. Alur Tugas & Prosedur Harian
1. **Memantau Kasus Anomali Bahan Bakar**:
   - Buka menu **Fuel Monitoring** di `/mining/fuel`.
   - Amati daftar kasus anomali yang terdeteksi oleh algoritma M3 `LinearBaselineRegression`.
   - Periksa deviasi persentase pemakaian solar vs baseline ton-kilometer armada.

2. **Resolusi Kasus Anomali & Dana Hold**:
   - Untuk kasus dengan status `PAYMENT_HELD`, evaluasi hasil investigasi lapangan atau tanggapan sanggahan kontraktor.
   - Pilih aksi:
     - **Release (Bebaskan)**: Jika deviasi terbukti wajar (misal akibat jalan becek ekstrem setelah hujan lebat). Sistem membalik posting escrow kembali ke hutang kontraktor:
       - Debit: `min:contractor_payment_hold:IDR`
       - Kredit: `ap:min:contractor:{party}:IDR`
     - **Deduct (Potong Tagihan)**: Jika deviasi terbukti merupakan penyelewengan bahan bakar. Sistem memotong tagihan kontraktor secara permanen dan mengakuinya sebagai pemulihan varians BBM:
       - Debit: `min:contractor_payment_hold:IDR`
       - Kredit: `min:fuel_variance_recovery:IDR`
   - Masukkan catatan resolusi wajib dan klik **Proses**.

3. **Verifikasi Rekonsiliasi Pembukuan**:
   - Jalankan audit dua sumber untuk memastikan kesetimbangan saldo akun hold:
     ```bash
     php artisan min:audit
     ```
   - Pastikan saldo `min:contractor_payment_hold:IDR` sama dengan akumulasi record status `HELD`.


---

## 4. Audit Rekonsiliasi Lima Ayat Jurnal Buku Besar (`MiningFase93LedgerPostingService`)
Sebagai bagian dari pengendalian internal penutupan buku harian dan bulanan, **Finance Officer** memverifikasi kelima jenis jurnal yang dibukukan oleh `Modules\Mining\Ledger\MiningFase93LedgerPostingService`:
1. **Pengeluaran Solar Tangki Site (`MINING_FUEL_EXPENSE`)**: Memastikan setiap pengurangan stok `current_stock_ml` di `min_fuel_storages` tercatat sebagai debit `expense:min:fuel:IDR` dan kredit `min:fuel_inventory:IDR` dengan kunci `min:fuel-dispense:{record_id}`.
2. **Tagihan Kontraktor Hauling (`MINING_HAUL_BILLING`)**: Memastikan penutupan shift (`SettleShiftHaulingBillingAction`) membukukan debit `expense:min:hauling_contractor:IDR` dan kredit `ap:min:contractor:{party}:IDR`.
3. **Penahanan Tagihan Anomali BBM (`MINING_FUEL_HOLD`)**: Memastikan setiap kasus berstatus `payment_held` memindahkan saldo dari `ap:min:contractor:{party}:IDR` ke `min:contractor_payment_hold:IDR`.
4. **Rilis Penahanan (`MINING_FUEL_RELEASE`)** dan **Pemotongan Definitif (`MINING_FUEL_RECOVERY`)**: Memastikan setiap penyelesaian sengketa di `/mining/fuel` mengosongkan porsi hold dan memindahkannya kembali ke utang kontraktor atau ke pendapatan pemulihan `min:fuel_variance_recovery:IDR`.
