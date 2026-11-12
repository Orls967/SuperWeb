# ADR-0054: Pembatasan Akses Multi-Dimensi (Entitas, Wilayah, Site, Proyek, Waktu)

**Status:** ACCEPTED  
**Tanggal:** 2026-11-12  
**Pengambil Keputusan:** Platform Architect, Pelaksana W8-P4  
**Konteks Gelombang:** Gelombang 8, Fase 188 (W8-P4.3 / W8-D54)

---

## Konteks & Masalah

Karyawan dan mitra di platform konglomerasi sering kali memiliki peran global (misal: `auditor` atau `dispatcher`), namun hak operasionalnya dibatasi pada wilayah hukum atau fasilitas fisik tertentu. Pemberian hak akses tanpa filter dimensi menyebabkan pelanggaran kepatuhan yurisdiksi dan risiko sabotase silang wilayah.

Diperlukan struktur data dimensi akses standar yang dapat dievaluasi secara konsisten oleh seluruh modul tanpa menduplikasi logika otorisasi.

---

## Keputusan Arsitektur

1. **Struktur Lima Dimensi Konteks Akses:**
   - `entity_id`: Pembatasan badan hukum legal (PT / unit usaha terafiliasi).
   - `region_id`: Pembatasan zona geografis (misal: `ID-JKT`, `ID-SBY`).
   - `site_id`: Pembatasan fasilitas fisik spesifik (misal: gudang WMS `WH-01`, pelabuhan `PRT-02`).
   - `project_id`: Pembatasan inisiatif atau proyek bertarget khusus.
   - `valid_window`: Rentang stempel waktu presisi detik (`valid_from` s.d. `valid_to`).
2. **Kaidah Pencocokan Dimensi:**
   - Nilai wildcard `*` atau `ALL` menandakan akses tidak dibatasi pada dimensi tersebut.
   - Jika dimensi kebijakan mendefinisikan himpunan nilai (misal: `['ID-JKT', 'ID-BDG']`), nilai konteks permintaan pengguna wajib merupakan anggota himpunan tersebut.
   - Jika jendela waktu telah berakhir (`now > valid_to`) atau belum dimulai (`now < valid_from`), evaluasi otomatis menolak permintaan.

---

## Konsekuensi

- **Positif:** Otorisasi sangat presisi, meminimalisir risiko eskalasi hak istimewa vertikal maupun horizontal.
- **Negatif:** Permintaan otorisasi harus selalu menyertakan metadata konteks operasional yang lengkap.
- **Kepatuhan:** Diuji pada skenario ribuan kombinasi dimensi di `AccessLifecycleTest`.

