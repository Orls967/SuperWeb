# ADR-0057: Folio Episode dan Pengakuan Pendapatan Rawat Inap Append-Only

## Konteks & Masalah
Pencatatan pendapatan rumah sakit sering mengalami pembatalan tindakan, koreksi tarif dokter, atau diskon hari rawat. Memodifikasi baris transaksi yang telah ada atau memposting pendapatan negatif merusak auditabilitas akuntansi buku besar.

## Keputusan Arsitektur
1. **Folio Episode Append-Only**: Setiap penambahan biaya perawatan (kamar, obat, tindakan, laboratorium) dicatat sebagai baris baru yang tidak boleh diubah atau dihapus (`update/delete` ditolak model).
2. **Koreksi Berbasis Reversal**: Setiap koreksi atau pembatalan biaya wajib diposting sebagai entri pembalik berlawanan dengan referensi baris asli.
3. **Akuntansi Tanpa Saldo Negatif**: Pendapatan rumah sakit (`hsp:hospital_revenue:IDR`) tidak pernah diposting dengan nilai negatif. Pembatalan diposting pada sisi debit akun pendapatan sebagai pembalik.
4. **Tarif Berversi**: Penentuan tarif kamar dan prosedur klinis mengacu pada `TariffVersion` dengan tanggal efektif berlaku yang ketat.

## Konsekuensi
- Menjamin kepatuhan penuh terhadap standar audit akuntansi double-entry SuperWeb.

