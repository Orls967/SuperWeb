# ADR-0056: Graf Klaster Identitas Multi-Pihak Berbobot & Penegakan Persetujuan Ganda

**Status:** ACCEPTED  
**Tanggal:** 2026-11-12  
**Pengambil Keputusan:** Data Protection Officer, Platform Architect, Pelaksana W8-P4  
**Konteks Gelombang:** Gelombang 8, Fase 188 (W8-P4.3 / W8-D56)

---

## Konteks & Masalah

Pengguna atau pelanggan dapat memiliki akun di beberapa lini bisnis SuperWeb (misal: tamu di Hotel, pasien di Rumah Sakit, pembeli di Ritel, dan siswa di Edukasi). Menggabungkan profil pengguna secara serampangan melanggar regulasi privasi data (UU PDP / GDPR) dan risiko bocornya data sensitif antar unit usaha (*unauthorized cross-sell*).

Diperlukan algoritma penautan identitas berbasis probabilistik terukur yang menjamin:
1. Tidak ada teks polos data sensitif yang digunakan sebagai kunci tautan.
2. Penautan hanya terjadi jika skor kecocokan mencapai ambang batas tinggi.
3. Penautan mutlak mensyaratkan izin eksplisit kedua belah pihak (*double consent*).

---

## Keputusan Arsitektur

1. **Pembobotan Atribut Kriptografis (`WeightedIdentityLinker` - C2):**
   - Nilai NIK, Email, dan Telepon di-hash menggunakan HMAC-SHA256 dengan secret salt platform sebelum dicocokkan.
   - Matriks bobot:
     - NIK Hash Cocok: 100 poin
     - Email Hash Cocok: 60 poin
     - No. Telepon Hash Cocok: 50 poin
     - Nama Normalisasi + DOB Hash Cocok: 40 poin
   - **Ambang Batas Kelayakan Tautan:** Skor total $\ge 90$ poin.
2. **Penegakan Persetujuan Ganda (*Double Consent Invariant*):**
   - Entitas tautan `core_identity_links` mencatat dua bendera: `source_consent` dan `target_consent`.
   - Data profil hanya dapat diagregasi jika `source_consent = true AND target_consent = true`.
   - Jika salah satu consent bernilai false atau ditarik kembali (*revoked*), status tautan seketika menjadi `BLOCKED_NO_CONSENT` dan profil target hanya dapat dilihat sebagai profil bayangan ter-anonimisasi (*shadow profile*).

---

## Konsekuensi

- **Positif:** Kepatuhan penuh terhadap hukum privasi data (UU PDP), pencegahan profiling ilegal antar entitas konglomerasi.
- **Negatif:** Proses penautan memerlukan alur persetujuan eksplisit dari kedua unit bisnis/pelanggan.
- **Kepatuhan:** Diuji melalui `AccessAlgorithmsTest` (C2) dan skenario penolakan pembagian data tanpa consent ganda.

