# Arsitektur Teknis: Logistik Lintas Batas & Pengalihan Aset Intercompany

## 1. Aliran Transaksi Pengiriman Barang Dagangan Lintas Batas (Cross-Border Stock Transfer)
Ketika entitas anak di Singapura (`SUB_SG`) mengirimkan komponen elektronika ke entitas anak di Indonesia (`SUB_ID`):
1. **Penerbitan Dokumen Ekspor (PEB)**:
   - Sisi Penjual (`SUB_SG`) menerbitkan faktur komersial ekspor dan mengajukan Pemberitahuan Ekspor Barang (PEB).
   - Pengurangan stok persediaan di Singapura pada harga pokok ekspor.
2. **Penerbitan Dokumen Impor (PIB)**:
   - Sisi Pembeli (`SUB_ID`) menerima pengiriman di pelabuhan Tanjung Priok / Bandara Soekarno-Hatta dan menerbitkan Pemberitahuan Impor Barang (PIB).
   - Nilai Pabean dihitung berbasis CIF (*Cost, Insurance, Freight*).
3. **Perhitungan Bea Masuk & Pajak Impor**:
   - Nilai CIF: Rp 100.000.000.
   - Bea Masuk (misal 5%): Rp 5.000.000.
   - Nilai Impor = CIF + Bea Masuk = Rp 105.000.000.
   - PPN Impor (11%): Rp 11.550.000 (dapat dikreditkan sebagai PPN Masukan).
   - PPh Pasal 22 Impor (2,5% ber-API): Rp 2.625.000 (uang muka pajak badan).
4. **Pembukuan Buku Besar di Sisi Pembeli**:
   - Debit `stk:inventory:SUB_ID:IDR` = `-105000000` (kapitalisasi CIF + Bea Masuk).
   - Debit `tax:vat_in:SUB_ID:IDR` = `-11550000` (PPN Masukan).
   - Debit `tax:prepaid_pph22:SUB_ID:IDR` = `-2625000` (PPh 22).
   - Kredit `ic:ap:SUB_SG:SUB_ID:IDR` = `+100000000` (Hutang ke penjual sebesar CIF).
   - Kredit `trs:bank_clearing:SUB_ID:IDR` = `+19175000` (Setor bea masuk & pajak ke kas negara).
   - Netto = $-105.000.000 - 11.550.000 - 2.625.000 + 100.000.000 + 19.175.000 = 0$.

---

## 2. Mutasi Aset Tetap Antar-Entitas (Fixed Asset Transfer)
Pengalihan mesin atau kendaraan operasional dari Entitas A ke Entitas B dilakukan dengan menjaga integritas akumulasi penyusutan historis:
1. **Data Aset Tetap Saat Pengalihan**:
   - Harga Perolehan Historis: Rp 500.000.000
   - Akumulasi Penyusutan Tercatat: Rp 200.000.000
   - Nilai Buku Neto (*Net Book Value* - NBV): Rp 300.000.000
   - Harga Pengalihan Arm's Length: Rp 300.000.000 (pada nilai wajar pasar / NBV)
2. **Posting Sisi Pengirim (Entitas A)**:
   - Debit `ast:accum_depr:A:IDR` = `-200000000` (menghapus akumulasi penyusutan).
   - Debit `ic:ar_asset_transfer:B:A:IDR` = `-300000000` (piutang pengalihan aset).
   - Kredit `ast:fixed_assets:A:IDR` = `+500000000` (menghapus harga perolehan aset).
   - Netto = $-200.000.000 - 300.000.000 + 500.000.000 = 0$.
3. **Posting Sisi Penerima (Entitas B)**:
   - Debit `ast:fixed_assets:B:IDR` = `-500000000` (mencatat aset pada harga perolehan historis grup).
   - Kredit `ast:accum_depr:B:IDR` = `+200000000` (meneruskan sisa masa manfaat dan akumulasi penyusutan).
   - Kredit `ic:ap_asset_transfer:A:B:IDR` = `+300000000` (hutang pengalihan aset).
   - Netto = $-500.000.000 + 200.000.000 + 300.000.000 = 0$.
4. **Eliminasi Konsolidasi**:
   - Saldo Piutang Pengalihan Aset di A dan Hutang Pengalihan Aset di B saling mengeliminasi pada cut-off konsolidasi bulanan tanpa menimbulkan distorsi nilai tercatat aset tetap grup.
