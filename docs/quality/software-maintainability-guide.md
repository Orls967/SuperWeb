# Panduan Indeks Kemudahan Pemeliharaan Perangkat Lunak (Maintainability Index)

## 1. Konsep & Formulasi Maintainability Index (MI)
Maintainability Index (MI) adalah metrik komposit yang dikembangkan oleh Software Engineering Institute (SEI) untuk mengevaluasi seberapa mudah suatu basis kode perangkat lunak dipelihara, dipahami, diperbaiki, dan diperluas di masa depan.

MI menggabungkan tiga dimensi independen:
1. **Halstead Volume ($V$)**: Mencerminkan kompleksitas leksikal dan ukuran vokabulari token program.
2. **Cyclomatic Complexity ($G$ / McCabe)**: Mencerminkan kompleksitas graf aliran kontrol logika percabangan.
3. **Lines of Code ($\text{LOC}$)**: Jumlah baris kode eksekutabel aktual.

### Formulasi Klasik SEI:
$$\text{MI}_{\text{raw}} = 171 - 5.2 \ln(V) - 0.23 G - 16.2 \ln(\text{LOC})$$

### Normalisasi Skala Microsoft (0 - 100):
Untuk mempermudah interpretasi pengembang, formula dinormalisasi ke rentang nilai 0 hingga 100:
$$\text{MI} = \max\left(0, \frac{\text{MI}_{\text{raw}} \cdot 100}{171}\right)$$

---

## 2. Kategori Rating Maintainability

Berdasarkan nilai indeks kemudahan pemeliharaan yang dihitung oleh `SoftwareMaintainabilityIndex`:

| Rentang Skor MI | Kategori Rating | Warna Indikator | Makna Arsitektural |
|---|---|---|---|
| **85.0 – 100.0** | **Excellent** | Hijau (`#15803d`) | Kode sangat bersih, modular, terfokus, dan sangat mudah dipelihara. |
| **65.0 – 84.9** | **Good** | Biru (`#0284c7`) | Kode memiliki kompleksitas terukur dan memenuhi standar pemeliharaan baik. |
| **50.0 – 64.9** | **Moderate** | Jingga (`#d97706`) | Kode mulai rumit. Perlu perhatian pada modularitas fungsi dan percabangan. |
| **< 50.0** | **Poor / Refactor Needed**| Merah (`#dc2626`) | Kode sangat berbelit (spaghetti code). Wajib dipecah dan direfaktor. |

---

## 3. Strategi Peningkatan Nilai Maintainability Index

Jika suatu file atau modul memiliki nilai MI di bawah 65, tim pengembang disarankan menerapkan tindakan kuratif:
1. **Pemisahan Metode Raksasa (Extract Method)**:
   - Memecah fungsi dengan baris $> 60$ LOC menjadi fungsi-fungsi kecil dengan tanggung jawab tunggal (Single Responsibility Principle).
2. **Penyederhanaan Percabangan (Guard Clauses)**:
   - Mengganti struktur `if-else` bersarang bertingkat dengan early return guard clauses, mengurangi nilai $G$ secara signifikan.
3. **Penggunaan Value Objects & Enumerations**:
   - Menggantikan string/integer primitif yang berulang dengan PHP Enum atau Value Object terdedikasi guna menekan Halstead Volume ($V$).

