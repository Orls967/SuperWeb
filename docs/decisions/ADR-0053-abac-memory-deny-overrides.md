# ADR-0053: Evaluator Otorisasi ABAC Murni Memori dengan Strategi Deny-Overrides

**Status:** ACCEPTED  
**Tanggal:** 2026-11-12  
**Pengambil Keputusan:** Platform Architect, Security Officer, Pelaksana W8-P4  
**Konteks Gelombang:** Gelombang 8, Fase 188 (W8-P4.3 / W8-D53)

---

## Konteks & Masalah

Pada arsitektur monolit modular 30 lini bisnis, pengecekan hak akses terjadi pada hampir setiap interaksi API dan HTTP. Mengandalkan kueri SQL relasional berulang ke tabel kebijakan pada setiap evaluasi menimbulkan lonjakan latensi I/O dan beban basis data yang tidak dapat dipertahankan. Selain itu, model RBAC murni terbukti tidak cukup fleksibel untuk mengakomodasi batasan multi-dimensi (entitas, wilayah operasional, site fasilitas, proyek, dan jendela waktu kerja).

Diperlukan mesin otorisasi berbasis atribut (*Attribute-Based Access Control / ABAC*) yang:
1. Mengeksekusi evaluasi murni di memori tanpa I/O.
2. Mematuhi strategi *Deny-Overrides* yang ketat (fail-closed).
3. Menghasilkan bukti kriptografis berupa sertifikat integritas evaluasi SHA-256.

---

## Keputusan Arsitektur

1. **Evaluator In-Memory Murni (`DenyOverridesEvaluator`):**
   - Aturan kebijakan di-cache dalam memori terstruktur sebagai array objek immutable.
   - Evaluasi terhadap konteks permintaan pengguna (`user_id`, `resource`, `action`, `attributes`) berlangsung tanpa kueri basis data.
2. **Strategi Deny-Overrides Mutlak:**
   - Aturan `DENY` memiliki preseden mutlak dibandingkan aturan `ALLOW`.
   - Jika terdapat setidaknya 1 aturan yang cocok dan menghasilkan `DENY`, keputusan akhir seketika adalah `DENY`.
   - Keputusan `ALLOW` hanya diberikan jika terdapat minimal 1 aturan `ALLOW` yang cocok dan tepat 0 aturan `DENY`.
   - Jika tidak ada aturan yang cocok sama sekali, evaluasi menghasilkan `DENY` secara *fail-closed*.
3. **Sertifikat Integritas SHA-256:**
   - Setiap evaluasi menghasilkan sertifikat yang memuat hash payload konteks, cap waktu, status keputusan, dan tanda tangan SHA-256 untuk keperluan audit forensik.

---

## Konsekuensi

- **Positif:** Evaluasi otorisasi sub-milidetik, keamanan *fail-closed* terjamin, bebas kebocoran izin akibat aturan ambigu.
- **Negatif:** Pengelolaan cache kebijakan harus disegarkan saat terjadi mutasi kebijakan di basis data melalui invalidasi event spine.
- **Kepatuhan:** Diuji melalui `AccessAlgorithmsTest` (C1) dan verifikasi vektor kriptografis.

