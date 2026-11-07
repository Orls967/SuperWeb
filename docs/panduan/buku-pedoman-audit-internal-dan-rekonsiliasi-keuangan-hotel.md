# Buku Pedoman Audit Internal & Rekonsiliasi Keuangan Hotel (Internal Audit Playbook)

Dokumen ini menjadi rujukan resmi bagi Auditor Keuangan, Manajer Keuangan, dan Night Auditor dalam memverifikasi keabsahan transaksi perhotelan.

---

## 1. Metodologi Audit Dua Sumber (Dual-Source Reconciliation Methodology)

Integritas pelaporan keuangan perhotelan bersandar pada rekonsiliasi independen antara data operasional fisik PMS dengan pembukuan buku besar (*General Ledger*) perbankan:
1. **Sumber Data Primer (PMS Operational Records)**:
   - Agregasi baris transaksi folio tamu fisik (`htl_folio_lines`) yang mencatat pembebanan harian sewa kamar, makanan restoran, minibar, dan binatu.
2. **Sumber Data Sekunder (Banking Ledger Entries)**:
   - Saldo mutasi kredit pada akun pendapatan sewa kamar (`htl:room_revenue:IDR`), hutang pajak PBJT (`htl:tax_payable:IDR`), dan hutang service charge (`htl:service_charge_payable:IDR`).
3. **Pemeriksaan Selisih (Discrepancy Check)**:
   - Command otomatis `php artisan hotel:audit` menghitung selisih matematis:
     $$\Delta = |	ext{Total Folio Room Charge} - 	ext{Total Ledger Room Revenue}|$$
   - Toleransi diskrepansi adalah mutlak **Rp 0 (NOL)**. Jika $\Delta > 0$, sistem audit otomatis menandai adanya transaksi liar atau kelalaian penjurnalan dan mengembalikan kode keluar non-zero (*Exit code 1*).

---

## 2. Prosedur Penutupan Akhir Bulan (Month-End Closing Checklist)

1. Rekonsiliasi seluruh piutang usaha korporat (*Corporate City Ledger Accounts Receivable*) dan verifikasi faktur tagihan yang telah melewati jatuh tempo 30 hari.
2. Perhitungan penyisihan denda no-show dan pembatalan non-refundable yang telah dialokasikan ke akun pendapatan penalti pembatalan.
3. Rekonsiliasi kas kecil resepsionis front desk (*petty cash float*) terhadap fisik uang tunai di brankas utama hotel.
4. Verifikasi bukti pembayaran pajak daerah PBJT ke kas daerah pemerintah kabupaten/kota setempat sesuai dengan surat ketetapan pajak daerah.
