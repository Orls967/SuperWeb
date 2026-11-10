# ADR-0197: Penegakan Otorisasi Terdepan (Policy-First) pada Pencarian Enterprise

**Nomor ADR:** `ADR-0197`  
**Status:** Diterima (*Accepted*)  
**Tanggal:** 2026-11-10  
**Penulis:** Pelaksana W8-P2  
**Keputusan Terkait:** `W8-D27`

---

## Konteks Bisnis & Masalah

Pada aplikasi multi-divisi dan multi-tenant, kebocoran data (*data leakage*) melalui kueri pencarian global merupakan salah satu kerentanan keamanan paling berisiko (IDOR - Insecure Direct Object References). Jika sistem mengembalikan cuplikan dokumen atau jumlah kecocokan sebelum memverifikasi hak akses pengguna, penyerang dapat menyimpulkan keberadaan dokumen rahasia melalui teknik *side-channel inference*.

---

## Keputusan Arsitektur

1. **Evaluasi Izin Sebagai Gerbang Biner Awal (Policy-First Gate):**  
   Pemeriksaan otorisasi peran (`allowed_roles`) dan batas penyewa (`tenant_id`) dieksekusi sebagai filter pertama. Jika pengguna tidak memiliki hak akses, skor relevansi langsung diset menjadi 0 dan dokumen dieliminasi dari daftar hasil.

2. **Sanitasi PII Otomatis:**  
   Setiap cuplikan pratinjau teks (*snippet preview*) yang disimpan atau dikembalikan secara otomatis membersihkan pola Nomor Induk Kependudukan (NIK), NPWP, nomor telepon, dan alamat email menjadi token `[REDACTED]`.

3. **Audit Log Kueri Terenkripsi:**  
   Setiap kueri pencarian dicatat pada `core_search_query_logs` bersama identitas pengguna, jumlah hasil, dan waktu latensi guna mendeteksi pola anomali atau percobaan probing data sensitif.

---

## Konsekuensi

- **Positif:** Jaminan mutlak perlindungan IDOR (zero information leak); kepatuhan terhadap regulasi privasi data korporat (UU PDP / GDPR); deteksi dini percobaan eksfiltrasi data.
- **Batasan:** Memerlukan sinkronisasi hak akses per entitas pada saat entitas diindeks ke dalam tabel `core_search_indices`.

