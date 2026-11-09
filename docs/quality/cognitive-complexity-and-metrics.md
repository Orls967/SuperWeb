# Panduan Analisis Kompleksitas Kognitif, Jalur Siklomatis & Metrik Halstead

## 1. Perbedaan Mendasar Kompleksitas Siklomatis vs Kognitif

Dalam rekayasa kualitas kode modern:
- **Cyclomatic Complexity (McCabe 1976)**: Mengukur jumlah jalur independen dalam graf aliran kontrol (control flow graph). Metrik ini sangat baik untuk menentukan jumlah minimum unit test kasus uji yang dibutuhkan untuk mencapai branch coverage penuh.
- Namun, McCabe memiliki kelemahan: ia memperlakukan struktur `switch-case` linier sama beratnya dengan lima blok `if-else` bersarang (nested loops), padahal tingkat kesulitan manusia dalam membaca dan memahami kode bersarang jauh lebih tinggi.
- **Cognitive Complexity (SonarSource 2017)**: Diciptakan untuk mengukur beban kognitif (mental effort) pengembang dalam memahami alur kontrol kode.

```
+------------------------------------------+------------------------------------------+
| Cyclomatic Complexity                    | Cognitive Complexity                     |
+------------------------------------------+------------------------------------------+
| Mengukur jumlah test cases yang perlu    | Mengukur kemudahan pemahaman oleh        |
| ditulis untuk menutup semua branch.      | manusia (human readability).             |
| Switch-case dengan 10 cabang = 10 poin.  | Switch-case dengan 10 cabang = 1 poin.   |
| Nested if-else di dalam loop = 2 poin.   | Nested if-else di dalam loop = 3+ poin   |
| (tidak ada penalti nesting depth).       | (mendapat penalti nesting depth).        |
+------------------------------------------+------------------------------------------+
```

---

## 2. Aturan Perhitungan Kompleksitas Kognitif

Kelas `CognitiveComplexityAnalyzer` menerapkan 3 aturan utama SonarSource:

### Aturan 1: Penambahan Dasar (+1)
Setiap struktur kontrol percabangan atau pengulangan berikut menambah $+1$ skor dasar:
- Percabangan: `if`, `ternary ?`, `switch`.
- Pengulangan: `for`, `foreach`, `while`, `do-while`.
- Penanganan eksepsi: `catch`.
- Urutan operator logika: `&&`, `||`.

### Aturan 2: Penalti Bersarang (Nesting Increments)
Ketika struktur kontrol berada di dalam struktur kontrol lain, penalti kedalaman ditambahkan:
$$\text{Penalti} = \text{Skor Dasar} + \text{Nesting Depth}$$

Contoh:
```php
// Nesting 0: +1 (if)
if ($user->isActive()) {
    // Nesting 1: +1 (for) + 1 (nesting) = +2
    foreach ($orders as $order) {
        // Nesting 2: +1 (if) + 2 (nesting) = +3
        if ($order->isPending()) {
            $order->process();
        }
    }
}
// Total Cognitive Complexity: 1 + 2 + 3 = 6 poin.
```

### Aturan 3: Pengecualian Struktur Tertentu
- Struktur `else` dan `elseif` menambah $+1$ poin dasar tetapi **tidak** dikenai penalti nesting tambahan.
- Deklarasi kelas dan fungsi pembungkus tidak menambah skor.

---

## 3. Metrik Sains Perangkat Lunak Halstead (1977)

Kelas `HalsteadMetricsCalculator` mengekstrak token operator dan operand dari kode PHP untuk menghitung parameter Halstead:

| Parameter Halstead | Simbol | Rumus Perhitungan |
|---|---|---|
| Operator Unik | $\eta_1$ | Jumlah operator aritmatika, logika, dan struktur kontrol unik |
| Operand Unik | $\eta_2$ | Jumlah variabel, konstanta, dan identifier unik |
| Total Operator | $N_1$ | Frekuensi kemunculan seluruh operator |
| Total Operand | $N_2$ | Frekuensi kemunculan seluruh operand |
| Program Vocabulary | $\eta$ | $\eta = \eta_1 + \eta_2$ |
| Program Length | $N$ | $N = N_1 + N_2$ |
| Volume Program | $V$ | $V = N \cdot \log_2(\eta)$ |
| Tingkat Kesulitan | $D$ | $D = \frac{\eta_1}{2} \cdot \frac{N_2}{\eta_2}$ |
| Usaha Komputasi | $E$ | $E = D \cdot V$ |
| Estimasi Defek Bug | $B$ | $B = \frac{V}{3000}$ |

Hasil komputasi Halstead dienkapsulasi ke dalam `HalsteadCertificate` dan divalidasi oleh `HalsteadCertificateVerifier` untuk menjamin tidak adanya pemalsuan kompleksitas kode.

---

## 4. Evaluasi NPath Complexity

Kelas `NPathComplexityEstimator` mengukur jumlah jalur eksekusi asiklik independen dalam suatu metode:
- Fungsi tanpa percabangan memiliki NPath $= 1$.
- Percabangan `if` menggandakan jalur ($\times 2$).
- Percabangan `case` dalam `switch` menambah jalur ($+1$).
- Pengulangan `for` / `while` menggandakan kemungkinan alur ($\times 2$).

Metode dengan NPath $> 200$ ditandai sebagai kandidat dekomposisi mendesak karena sulit untuk diuji secara komprehensif.

