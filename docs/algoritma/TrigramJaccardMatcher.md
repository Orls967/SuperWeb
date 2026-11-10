# Algoritma A5: Trigram Jaccard Matcher

**Modul:** `Shared` & `Core`  
**Namespace:** `Modules\Shared\Domain\Algorithms\Ui\TrigramJaccardMatcher`  
**Status Sertifikasi:** BERSERTIFIKAT PENUH (Garansi Toleransi Saltik Deterministik)

---

## 1. Landasan Teori & Rumus Matematis

Algoritma Trigram Jaccard Matcher mengombinasikan pembagian leksikal berbasis n-gram 3-karakter dengan indeks kesamaan himpunan Paul Jaccard (1912).

Formula matematis:
$$J(A, B) = \frac{|A \cap B|}{|A \cup B|}$$

Di mana:
- $A$: Himpunan trigram dari string kueri input yang telah dinormalisasi dan diapit oleh simbol sentry (`$$...$$`).
- $B$: Himpunan trigram dari leksikon kandidat atau kata kunci entitas bisnis.
- $|A \cap B|$: Jumlah irisan elemen unik yang sama antara himpunan $A$ dan $B$.
- $|A \cup B|$: Jumlah gabungan elemen unik antara himpunan $A$ dan $B$.

Contoh pemecahan trigram untuk string `"bengkel"`:
`["$$b", "$be", "ben", "eng", "ngk", "gke", "kel", "el$", "l$$"]` (total 9 trigram).

---

## 2. Parameter Toleransi & Ambang Batas

- **Ambang Penerimaan Saltik (Typo Tolerance Threshold):** $J \ge 0.35$ ($3.500$ bps). Bila skor berada di rentang ini, kata kunci dianggap sebagai kandidat koreksi kesalahan ketik (*did-you-mean suggestion*).
- **Ambang Kecocokan Tinggi (High Confidence Match):** $J \ge 0.70$ ($7.000$ bps). Menandakan perbedaan hanya 1 transposisi karakter atau 1 huruf hilang/lebih.
- **Transformasi Integer:** Koefisien kesamaan direpresentasikan dalam basis points: $\text{Similarity}_{\text{bps}} = \lfloor J(A, B) \times 10.000 \rfloor$.

---

## 3. Vektor Uji & Nilai Ekspektasi

| Kueri Input ($Q$) | Kandidat Leksikal ($C$) | Trigram $Q$ | Trigram $C$ | Irisan / Gabungan | Nilai Jaccard | Status |
|---|---|---|---|---|---|---|
| `"bengkel"` | `"bengkel"` | 9 | 9 | 9 / 9 | 10.000 bps (1.00) | Identik |
| `"bngkel"` | `"bengkel"` | 8 | 9 | 6 / 11 | 5.454 bps (0.54) | Saran Saltik |
| `"kontrak"` | `"kontraktor"` | 9 | 12 | 8 / 13 | 6.153 bps (0.61) | Saran Prefiks |
| `"kapal"` | `"pesawat"` | 7 | 9 | 0 / 16 | 0 bps (0.00) | Ditolak |

---

## 4. Karakteristik Kompleksitas & Implementasi PHP

- **Kompleksitas Waktu:** $\mathcal{O}(|A| + |B|)$ untuk pemotongan substring 3-karakter dan operasi `array_intersect` / `array_unique`.
- **Kompleksitas Ruang:** $\mathcal{O}(|A| + |B|)$ memori alokasi array trigram.
- **Penanganan Tepi:** String dengan panjang $< 3$ karakter dipadatkan dengan spasi sentry tambahan sehingga tetap menghasilkan set minimal 3 trigram.

---

## 5. Sertifikat Integritas Algoritma

Hasil pencocokan disegel dengan `JaccardCertificateVerifier`:
```json
{
  "algorithm": "TRIGRAM_JACCARD_V1",
  "query": "bngkel",
  "candidate": "bengkel",
  "similarity_bps": 5454,
  "is_suggested": true,
  "certificate_seal": "3c89ab41..."
}
```
