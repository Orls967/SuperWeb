# Pedoman Tata Kelola: Kebijakan Reaktivasi Poin Kedaluwarsa & Kompensasi Layanan — Fase 112

## Bab 1: Kondisi Luar Biasa untuk Reaktivasi Poin
Secara umum, poin yang telah melewati masa berlaku 730 hari akan diproses menjadi pendapatan breakage dan tidak dapat dipulihkan. Namun, demi menjaga kepuasan pelanggan loyal tingkat tinggi, Direktur Layanan Pelanggan memiliki diskresi terbatas untuk menyetujui permohonan reaktivasi poin (points reactivation) dalam kondisi luar biasa:
1. Anggota mengalami rawat inap medis intensif yang dibuktikan dengan surat keterangan resmi rumah sakit.
2. Anggota bertugas di wilayah penugasan militer atau misi kemanusiaan tanpa akses internet selama lebih dari 6 bulan berturut-turut.
3. Kesalahan teknis sistem notifikasi di mana email pengingat kedaluwarsa gagal terkirim (bounced server failure).

---

## Bab 2: Prosedur Pemulihan Saldo & Posting Akuntansi Penyeimbang
- Permohonan reaktivasi diajukan melalui portal layanan pelanggan dan diverifikasi oleh Tim Kepatuhan.
- Bila disetujui, sistem menerbitkan batch baru dengan kuantitas poin yang sama dan masa aktif darurat selama 90 hari kalender.
- Jurnal penyeimbang akuntansi:
  - **Debit:** `htl:loyalty_breakage_revenue:IDR` (Membalik sebagian pengakuan pendapatan breakage sebelumnya).
  - **Kredit:** `htl:loyalty_points_liability:IDR` (Mengaktifkan kembali liabilitas poin beredar).

---

## Bab 3: Batasan Frekuensi & Pengawasan Audit
1. Setiap anggota hanya diperkenankan mengajukan permohonan reaktivasi poin maksimal 1 kali dalam kurun waktu 3 tahun kalender.
2. Jumlah poin maksimal yang dapat direaktivasi dibatasi hingga 50.000 PTS per permohonan.
3. Seluruh berkas pendukung dan persetujuan otorisasi digital diarsipkan dalam sistem manajemen dokumen hukum selama 5 tahun untuk keperluan audit eksternal.
