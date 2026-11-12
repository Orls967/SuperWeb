# ADR-0059: Kampanye Re-Sertifikasi Akses Periodik dengan Pencabutan Otomatis

**Status:** ACCEPTED  
**Tanggal:** 2026-11-12  
**Pengambil Keputusan:** Compliance Officer, Platform Architect, Pelaksana W8-P4  
**Konteks Gelombang:** Gelombang 8, Fase 188 (W8-P4.3 / W8-D59)

---

## Konteks & Masalah

Fenomena penumpukan hak akses berlebih (*privilege creep*) terjadi ketika karyawan berpindah divisi atau proyek namun izin akses lama tidak pernah dicabut. Standar keamanan enterprise (ISO 27001, SOC 2) mewajibkan audit tinjauan akses secara berkala oleh manajer lini atau penanggung jawab sistem.

Tanpa mekanisme penegakan tenggat waktu otomatis, kampanye re-sertifikasi sering kali diabaikan oleh para penyelia.

---

## Keputusan Arsitektur

1. **Entitas Kampanye Re-Sertifikasi (`CoreAccessRecertificationCampaign`):**
   - Mendefinisikan cakupan audit (misal: per divisi, per tenant, atau per tingkat risiko hak akses).
   - Memiliki jendela waktu penyelesaian tegas (misal: 14 hari kerja) dan penanggung jawab pemeriksa.
2. **Item Re-Sertifikasi (`CoreAccessRecertificationItem`):**
   - Setiap grant aktif di dalam cakupan dimasukkan sebagai item audit dengan 3 pilihan respon:
     - `CERTIFIED`: Hak akses dikonfirmasi masih dibutuhkan $\implies$ Masa berlaku grant diperpanjang.
     - `REVOKE_REQUESTED`: Hak akses tidak lagi dibutuhkan $\implies$ Grant seketika dicabut (`revoked`).
     - `PENDING`: Menunggu respon penyelia.
3. **Pencabutan Otomatis Saat Tenggat Berakhir (`Auto-Revoke on Deadline`):**
   - Perintah `access:recert-campaign --sweep` memeriksa kampanye yang telah melewati tenggat waktu.
   - Seluruh item yang masih berstatus `PENDING` secara otomatis dicabut (*auto-revoked*), transisi grant diarahkan ke `revoked` dengan alasan `RECERTIFICATION_DEADLINE_EXPIRED`.

---

## Konsekuensi

- **Positif:** Penghapusan *privilege creep* secara otomatis, pemenuhan standar tata kelola kepatuhan global.
- **Negatif:** Memerlukan keaktifan para penyelia dalam merespon kampanye sebelum batas tenggat berakhir.
- **Kepatuhan:** Diuji di `AccessLifecycleTest` skenario kampanye dan auto-revocation.

