# Dokumentasi Algoritma: `BankReconciliationMatcher`

**Namespace**: `Modules\Treasury\Domain\Algorithms\BankReconciliationMatcher`  
**Fase Terkait**: Fase 48 — Multi-Currency & Treasury  
**Sertifikat Terkait**: `BankReconciliationCertificate`, `BankReconciliationCertificateVerifier`  

---

## 1. Intuisi Bisnis & Prinsip Kerja
Rekonsiliasi bank adalah proses mencocokkan mutasi kas pada rekening koran perbankan (*bank statement*) dengan catatan buku kas perusahaan (*general ledger bank account*). Algoritma ini dirancang dengan pendekatan deterministik 3-tahap tanpa mengubah data mutasi perbankan:
1. **Tahap 1 — Exact Matching**: Menemukan pasangan 1-to-1 yang memiliki nilai nominal identik dan nomor referensi unik yang persis sama.
2. **Tahap 2 — Tolerance Window Matching**: Untuk mutasi yang tidak memiliki nomor referensi identik, sistem memadankan transaksi dengan selisih nominal kecil ($\le 500$ minor unit) dan selisih tanggal sempit ($\le 3$ hari kalender) secara greedy dan leksikografis deterministik.
3. **Tahap 3 — Bounded Many-to-One Matching**: Memadankan satu transaksi bank (misal setoran gabungan) dengan kombinasi 2 hingga 4 transaksi buku kas yang jumlah nominalnya tepat sama, dengan batas maksimum 12 kandidat terdekat ($\le 5$ hari) untuk menjamin batas komputasi konstan ($\le 781$ subset).

---

## 2. Formulasi & Tahapan Algoritma

### Tahap 1: Exact Match
- **Input**: Himpunan mutasi bank $\mathcal{B}$ dan mutasi buku $\mathcal{L}$.
- **Kondisi Cocok**:
  $$(b, l) \in \mathcal{B} \times \mathcal{L} \iff b.\text{amount} = l.\text{amount} \land b.\text{ref} \ne \text{""} \land b.\text{ref} = l.\text{ref}$$
- Mutasi yang cocok dikeluarkan dari himpunan kandidat berikutnya.

### Tahap 2: Tolerance Match
- **Kondisi Kandidat**:
  $$|b.\text{amount} - l.\text{amount}| \le 500 \land |b.\text{day} - l.\text{day}| \le 3$$
- **Aturan Pengurutan**:
  Untuk setiap mutasi bank $b \in \mathcal{B}_{\text{rem}}$ yang diurutkan berdasarkan `b.id ASC`, cari mutasi buku $l \in \mathcal{L}_{\text{rem}}$ yang meminimalkan tuppel:
  $$\operatorname{Cost}(b, l) = (|b.\text{amount} - l.\text{amount}|, |b.\text{day} - l.\text{day}|, l.\text{id})$$
- Pasangan optimal dikunci dan dikeluarkan dari himpunan.

### Tahap 3: Bounded Many-to-One Match
- Untuk setiap baris bank tersisa $b \in \mathcal{B}_{\text{rem}}$ dengan $b.\text{amount} > 0$ (terurut `b.id ASC`):
  1. Himpunan kandidat buku $\mathcal{C}_b = \{ l \in \mathcal{L}_{\text{rem}} \mid l.\text{amount} > 0 \land l.\text{amount} < b.\text{amount} \land |b.\text{day} - l.\text{day}| \le 5 \}$.
  2. Urutkan $\mathcal{C}_b$ berdasarkan `(l.day ASC, l.id ASC)` dan ambil paling banyak $K_{\max} = 12$ elemen pertama.
  3. Evaluasi kombinasi subset $S \subseteq \mathcal{C}_b$ dengan ukuran $|S| \in \{2, 3, 4\}$ yang memenuhi:
     $$\sum_{s \in S} s.\text{amount} = b.\text{amount}$$
  4. Aturan pemilihan tie-break:
     - Ukuran subset terkecil didahulukan ($|S| = 2$ sebelum 3, sebelum 4).
     - Jika ada beberapa subset dengan ukuran yang sama, pilih subset dengan urutan ID leksikografis terkecil.
  5. Pasangkan $(b, S)$, lalu keluarkan $b$ dan seluruh elemen $S$ dari daftar mutasi tersisa.

---

## 3. Contoh Perhitungan Langkah Demi Langkah (Vektor Uji)

### Data Masukan:
- **Bank**:
  - `B1`: Hari 1, 1.000.000 IDR, ref `INV-1`
  - `B2`: Hari 2, 2.500.000 IDR, ref kosong `""`
  - `B3`: Hari 3, 750.500 IDR, ref `INV-9`
  - `B4`: Hari 5, 3.000.000 IDR, ref `PAY-7`
  - `B5`: Hari 6, 99.999 IDR, ref `X`
- **Buku**:
  - `L1`: Hari 1, 1.000.000 IDR, ref `INV-1`
  - `L2`: Hari 2, 2.500.400 IDR, ref kosong `""`
  - `L3`: Hari 4, 750.500 IDR, ref `INV-9-A`
  - `L4`: Hari 4, 1.200.000 IDR, ref `-`
  - `L5`: Hari 5, 1.000.000 IDR, ref `-`
  - `L6`: Hari 5, 800.000 IDR, ref `-`
  - `L7`: Hari 6, 1.000.000 IDR, ref `-`

### Langkah Eksekusi:
1. **Tahap 1 (Exact)**:
   - `B1` (1.000.000, `INV-1`) dan `L1` (1.000.000, `INV-1`): Jumlah sama dan nomor referensi sama persis $\implies$ **Matched: `(B1, L1)`**.
2. **Tahap 2 (Toleransi)**:
   - `B2` (2.500.000, Hari 2): Kandidat `L2` (2.500.400, Hari 2). Selisih nominal $|2.500.000 - 2.500.400| = 400 \le 500$, selisih hari $0 \le 3$. $\implies$ **Matched: `(B2, L2)`**, selisih nominal 400.
   - `B3` (750.500, Hari 3): Kandidat `L3` (750.500, Hari 4). Selisih nominal $0 \le 500$, selisih hari $1 \le 3$. $\implies$ **Matched: `(B3, L3)`**, selisih nominal 0.
3. **Tahap 3 (Many-to-One)**:
   - Mutasi bank tersisa: `B4` (3.000.000, Hari 5) dan `B5` (99.999, Hari 6).
   - Buku tersisa: `L4` (1.200.000), `L5` (1.000.000), `L6` (800.000), `L7` (1.000.000).
   - Untuk `B4`:
     - Kandidat dalam jendela $\le 5$ hari: `L4` (Hari 4), `L5` (Hari 5), `L6` (Hari 5), `L7` (Hari 6).
     - Cari subset jumlah 3.000.000:
       - Ukuran 2: Tidak ada yang berjumlah 3.000.000 (maks $1.200.000 + 1.000.000 = 2.200.000$).
       - Ukuran 3:
         - Kombinasi $[L4, L5, L6] \implies 1.200.000 + 1.000.000 + 800.000 = 3.000.000$. (Valid!)
         - Kombinasi $[L4, L6, L7] \implies 1.200.000 + 800.000 + 1.000.000 = 3.000.000$. (Valid!)
       - Pemilihan leksikografis: $[L4, L5, L6]$ vs $[L4, L6, L7]$. Elemen kedua `L5` < `L6`, sehingga $[L4, L5, L6]$ dipilih! $\implies$ **Matched: `(B4, [L4, L5, L6])`**.
4. **Sisa Tak Terpadankan**:
   - Bank tak terpadankan: `[B5]` (99.999).
   - Buku tak terpadankan: `[L7]` (1.000.000).

---

## 4. Kompleksitas & Karakteristik Komputasi
- **Batas Kombinatorial Subset**:
  $$\binom{12}{2} + \binom{12}{3} + \binom{12}{4} = 66 + 220 + 495 = 781$$
  Maksimum 781 evaluasi per transaksi bank, sehingga runtime terikat ketat $O(N_B)$.
- **Ruang Memori**: $O(N_B + N_L)$.
- **Lokasi Berkas Kode**:
  - `modules/Treasury/Domain/Algorithms/BankReconciliationMatcher.php`
  - `modules/Treasury/Domain/Algorithms/Certificates/BankReconciliationCertificate.php`
  - `modules/Treasury/Domain/Algorithms/Certificates/BankReconciliationCertificateVerifier.php`
