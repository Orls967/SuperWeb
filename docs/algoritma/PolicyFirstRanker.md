# Algoritma A6: Policy-First Ranker

**Modul:** `Shared` & `Core`  
**Namespace:** `Modules\Shared\Domain\Algorithms\Ui\PolicyFirstRanker`  
**Status Sertifikasi:** BERSERTIFIKAT PENUH (Garansi Zero-Leak Otorisasi RBAC)

---

## 1. Landasan Teori & Rumus Matematis

Policy-First Ranker adalah mesin perankingan multi-kriteria yang menggabungkan kepatuhan otorisasi peran (RBAC Sandhu et al., 1996), skor relevansi teks BM25, faktor toleransi saltik Jaccard, peluruhan waktu (*recency decay*), dan bobot prioritas tipe entitas.

Formula matematis:
$$\text{PolicyFactor}(D, U) = \begin{cases} 1, & \text{jika } \text{RoleMatch}(D, U) \land \text{TenantMatch}(D, U) \\ 0, & \text{lainnya} \end{cases}$$

$$\text{FinalScore}(D, Q, U) = \text{PolicyFactor}(D, U) \times \left( w_1 \cdot \text{BM25}_{\text{norm}} + w_2 \cdot \text{Jaccard}_{\text{norm}} + w_3 \cdot \text{RecencyScore} + w_4 \cdot \text{EntityWeight} \right)$$

Di mana bobot kanonikal sistem ($w_1 + w_2 + w_3 + w_4 = 1.0$):
- $w_1 = 0.50$ (Relevansi leksikal BM25)
- $w_2 = 0.20$ (Kecocokan fuzzy Jaccard)
- $w_3 = 0.15$ (Kebaruan tanggal dokumen / recency)
- $w_4 = 0.15$ (Prioritas domain entitas bisnis)

Fungsi peluruhan waktu (*recency score*):
$$\text{RecencyScore} = \max \left( 0, 10.000 - (\Delta t_{\text{days}} \times 100) \right)$$
di mana $\Delta t_{\text{days}}$ adalah selisih hari antara waktu pembaruan dokumen dengan waktu kueri saat ini.

---

## 2. Jaminan Keamanan Zero-Leak IDOR Protection

Sesuai standar zero-trust enterprise:
1. Pengecekan `PolicyFactor` dieksekusi **sebelum** pembobotan kueri teks diproses lebih lanjut.
2. Jika pengguna dengan role `mechanic` mencoba mencari dokumen dengan kata kunci nomor kontrak legal yang hanya diizinkan untuk `legal_counsel` dan `admin`, `PolicyFactor` bernilai 0.
3. Entitas dengan skor 0 langsung dieksklusi dari daftar respons JSON, menjamin nihil kebocoran metadata (*zero information leakage*).
4. Sanitasi otomatis PII dilakukan pada cuplikan ringkasan teks sebelum ditampilkan di antarmuka web.

---

## 3. Vektor Uji & Nilai Ekspektasi

| Entitas | Role Pengguna ($U$) | Izin Entitas ($D$) | BM25 (bps) | Jaccard (bps) | $\Delta t$ (hari) | Final Score (bps) |
|---|---|---|---|---|---|---|
| Work Order Bengkel | `service_advisor` | `['service_advisor', 'admin']` | 6.000 | 8.000 | 2 | 7.150 bps |
| Dokumen Kontrak Legal | `mechanic` | `['legal_counsel', 'admin']` | 9.000 | 9.000 | 1 | **0 bps (Ditolak RBAC)** |
| Manifest Kapal Kargo | `admin` | `['marine_officer', 'admin']` | 4.000 | 5.000 | 10 | 5.350 bps |

---

## 4. Karakteristik Kompleksitas & Implementasi PHP

- **Kompleksitas Waktu:** $\mathcal{O}(K \log K)$ di mana $K$ adalah jumlah entitas kandidat yang lolos evaluasi kebijakan otorisasi.
- **Kompleksitas Ruang:** $\mathcal{O}(K)$ untuk penampung skor terurut.
- **Determinisme:** Jika dua entitas memiliki skor akhir yang sama, pengurutan sekunder didasarkan pada ID entitas secara leksikografis menaik.

---

## 5. Sertifikat Integritas Algoritma

Hasil peringkat dienkapsulasi dengan segel kriptografis `PolicyRankingCertificateVerifier`:
```json
{
  "algorithm": "POLICY_FIRST_RANKER_V1",
  "user_role": "service_advisor",
  "entity_id": "WO-2026-001",
  "policy_allowed": true,
  "final_score_bps": 7150,
  "certificate_seal": "8a71ef99..."
}
```

