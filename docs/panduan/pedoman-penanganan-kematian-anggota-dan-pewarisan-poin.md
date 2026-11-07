# Pedoman Hukum & Prosedur: Pewarisan Poin & Suksesi Akun Keanggotaan — Fase 112

## Bab 1: Kedudukan Hukum Poin Loyalitas sebagai Aset Hak Kebendaan
Dalam dinamika program loyalitas perhotelan modern berskala jutaan poin (senilai puluhan hingga ratusan juta rupiah), timbul pertanyaan hukum terkait status kepemilikan poin apabila pemegang akun meninggal dunia (deceased member succession). Berdasarkan ketentuan syarat dan ketentuan umum Global Travel Pass:
1. Poin PTS diakui sebagai hak kontraktual yang dapat dialihkan kepada ahli waris sah yang ditunjuk dalam surat wasiat atau penetapan pengadilan negeri yang berwenang.
2. Pengalihan saldo poin tidak dikenakan biaya transfer atau potongan komisi platform.
3. Tingkat keanggotaan (tier) tidak dapat diwariskan secara otomatis; ahli waris menerima saldo poin murni dan ditempatkan pada tier dasar atau tier yang sesuai dengan histori transaksinya sendiri.

---

## Bab 2: Dokumen Persyaratan & Verifikasi Legalitas
Ahli waris atau eksekutor warisan wajib menyerahkan berkas administrasi berikut ke bagian Legal & Compliance Hotel:
1. Salinan Akta Kematian resmi yang diterbitkan oleh Dinas Kependudukan dan Catatan Sipil (Disdukcapil).
2. Salinan Surat Keterangan Hak Waris (SKHW) dari notaris atau penetapan pengadilan yang berkekuatan hukum tetap.
3. Salinan Kartu Tanda Penduduk (KTP) dan Kartu Keluarga (KK) ahli waris pemohon.
4. Formulir Permohonan Pengalihan Poin Suksesi Akun yang ditandatangani di atas meterai resmi.

---

## Bab 3: Prosedur Eksekusi Pemindahan Poin & Penutupan Akun
1. Tim Legal melakukan verifikasi keaslian dokumen dalam jangka waktu SLA maksimal 14 hari kerja.
2. Begitu verifikasi disetujui, Petugas Kepatuhan menjalankan aksi pemindahan saldo melalui antarmuka konsol:
   ```bash
   php artisan hotel:transfer-deceased-loyalty-points --from-pass="TP-DECEASED-XXXX" --to-pass="TP-HEIR-YYYY" --approved-by="LEGAL_COUNSEL"
   ```
3. Seluruh batch poin aktif dipindahkan ke akun ahli waris dengan mempertahankan sisa masa kedaluwarsa aslinya.
4. Akun Travel Pass almarhum diubah statusnya menjadi `CLOSED_DECEASED`, riwayat transaksi diarsipkan untuk keperluan kepatuhan audit selama 10 tahun, dan notifikasi konfirmasi dikirimkan kepada ahli waris.

