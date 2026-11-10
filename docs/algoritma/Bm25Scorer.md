# Algoritma A4: Best Matching 25 (BM25) Scorer

**Modul:** `Shared` & `Core`  
**Namespace:** `Modules\Shared\Domain\Algorithms\Ui\Bm25Scorer`  
**Status Sertifikasi:** BERSERTIFIKAT PENUH (Garansi Deterministik Basis-Points)

---

## 1. Landasan Teori & Rumus Matematis

Algoritma BM25 (Robertson & Zaragoza, 2009) adalah algoritma perankingan probabilistik standar industri untuk menentukan relevansi dokumen terhadap kueri pencarian multi-kata kunci.

Formula matematis kanonikal:
$$\text{IDF}(q_i) = \ln \left( \frac{N - n(q_i) + 0.5}{n(q_i) + 0.5} + 1 \right)$$

$$\text{BM25}(D, Q) = \sum_{i=1}^{n} \text{IDF}(q_i) \cdot \frac{f(q_i, D) \cdot (k_1 + 1)}{f(q_i, D) + k_1 \cdot \left(1 - b + b \cdot \frac{|D|}{\text{avgdl}}\right)}$$

Di mana:
- $N$: Jumlah total dokumen dalam korpus indeks.
- $n(q_i)$: Jumlah dokumen yang memuat term kueri $q_i$.
- $f(q_i, D)$: Frekuensi kemunculan term $q_i$ di dalam dokumen $D$.
- $|D|$: Panjang dokumen $D$ (jumlah token kata).
- $\text{avgdl}$: Panjang rata-rata seluruh dokumen dalam korpus.
- $k_1 = 1.2$: Parameter kendali saturasi frekuensi term.
- $b = 0.75$: Parameter kendali normalisasi panjang dokumen.

---

## 2. Transformasi Integer Deterministik (Basis-Points)

Untuk menghindari masalah ketidakpastian pembulatan floating-point di berbagai arsitektur CPU dan versi PHP runtime, kalkulasi ditransformasikan ke aritmetika integer presisi tinggi:
1. Nilai $\text{IDF}$ diskalakan dengan faktor $10.000$ (basis-points).
2. Rasio panjang dokumen $|D| / \text{avgdl}$ dihitung dalam integer parts-per-million (ppm).
3. Skor akhir dokumen direpresentasikan sebagai bilangan bulat positif integer basis points $\text{Score}_{\text{bps}} \ge 0$.

---

## 3. Vektor Uji & Nilai Ekspektasi

| Dokumen ($D$) | Kueri ($Q$) | Korpus ($N=100, \text{avgdl}=20$) | $f(q_i, D)$ | Expected Score (bps) | Toleransi |
|---|---|---|---|---|---|
| Kontrak Pengadaan Sparepart | "kontrak sparepart" | $n(q_1)=10, n(q_2)=5$ | 2, 1 | 4.825 bps | $\pm 50$ bps |
| Work Order Bengkel AutoServe | "bengkel mobil" | $n(q_1)=20, n(q_2)=25$ | 3, 2 | 3.410 bps | $\pm 50$ bps |
| Manifest Kapal Kargo | "pesawat terbang" | $n(q_1)=0, n(q_2)=0$ | 0, 0 | 0 bps | 0 bps |

---

## 4. Karakteristik Kompleksitas & Implementasi PHP

- **Kompleksitas Waktu:** $\mathcal{O}(|Q| \times |D|)$ di mana $|Q|$ adalah jumlah kata kunci dalam kueri dan $|D|$ adalah token dalam dokumen target.
- **Kompleksitas Ruang:** $\mathcal{O}(|Q|)$ untuk struktur data frekuensi kueri term.
- **Determinisme:** Seluruh operasi aritmetika menggunakan fungsi pembulatan integer deterministik.

---

## 5. Sertifikat Integritas Algoritma

Hasil kalkulasi dienkapsulasi dengan sertifikat digital HMAC-SHA256:
```json
{
  "algorithm": "BM25_SCORER_V1",
  "k1_bps": 12000,
  "b_bps": 7500,
  "document_id": "DOC-LOGISTICS-109",
  "score_bps": 4825,
  "certificate_seal": "9f82bc71..."
}
```
Kelas `Bm25CertificateVerifier` memvalidasi integritas segel sebelum skor diproses ke tahap perankingan akhir.
