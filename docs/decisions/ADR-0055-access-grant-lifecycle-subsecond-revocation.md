# ADR-0055: Siklus Hidup Access Grant 8 Status & Mekanisme Pencabutan Sub-Detik

**Status:** ACCEPTED  
**Tanggal:** 2026-11-12  
**Pengambil Keputusan:** Security Officer, Platform Architect, Pelaksana W8-P4  
**Konteks Gelombang:** Gelombang 8, Fase 188 (W8-P4.3 / W8-D55)

---

## Konteks & Masalah

Pengelolaan hak akses sementara (*just-in-time access*) dan peran dinamis membutuhkan jejak audit formal sejak pengajuan hingga kedaluwarsa. Sistem token statis lama (seperti JWT tanpa verifikasi versi) memiliki kelemahan fatal: ketika hak akses dicabut di basis data, token pengguna tetap dapat digunakan hingga masa kedaluwarsa berakhir.

Dalam insiden keamanan, pencabutan izin akses (*revocation*) wajib berlaku seketika ($\le 1$ detik) di seluruh simpul komputasi platform.

---

## Keputusan Arsitektur

1. **State Machine Access Grant 8 Status:**
   - Transisi status: `requested` $\to$ `policy_checked` $\to$ `approved` $\to$ `provisioned` $\to$ `active` $\to$ `suspended` $\to$ `revoked` $\to$ `expired`.
   - Riwayat transisi dicatat secara *append-only* pada `core_access_grant_transitions` dengan larangan keras operasi `update` atau `delete`.
2. **Pencabutan Sub-Detik Berbasis Versi (`grant_version`):**
   - Setiap pengguna atau entitas grant memiliki atribut integer `grant_version` yang dicatat di cache terdistribusi (Redis/APCu) dan tabel pengguna.
   - Tindakan pencabutan (`revoke()`) secara atomik menaikkan `grant_version` dan memperbarui stempel `revoked_at`.
   - Gate verifikasi membandingkan versi pada token dengan versi saat ini di cache. Jika versi pada token lebih rendah dari cache, permintaan ditolak `403 Forbidden` seketika tanpa perlu kueri relasional penuh.
3. **Penyapuan Otomatis Izin Kedaluwarsa:**
   - Perintah terjadwal `access:expire-grants` menyapu seluruh grant dengan `valid_to < now()` yang masih berstatus `active` atau `suspended` dan mengubahnya menjadi `expired`.

---

## Konsekuensi

- **Positif:** Penghapusan window kerentanan pasca-pencabutan hak akses, kepatuhan audit SOC2/ISO27001 terpenuhi.
- **Negatif:** Kebutuhan mekanisme cache yang konsisten di semua node pemroses.
- **Kepatuhan:** Diuji di `AccessLifecycleTest` dengan skenario pembatalan hak akses efektif dalam sub-detik.

