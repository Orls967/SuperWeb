# Spesifikasi Matematis & Algoritma: DecisionDensityCounter (A2)

## 1. Landasan Teoretis

Kerapatan Keputusan (*Decision Density*) merupakan indikator kebersihan kode yang mengukur proporsi struktur percabangan logika (decision points) terhadap total baris kode logis (Logical Lines of Code / LLOC) atau total method dalam suatu kelas.

Tujuan utama dari penghitung kerapatan keputusan ini adalah mencegah:
1. **Anemic Padding**: Penambahan ratusan DTO kosong tanpa logika bisnis untuk memompa metrik baris kode (LOC) secara artifisial.
2. **Monolithic Spaghetti**: Method dengan kompleksitas percabangan yang terlalu padat yang sulit diuji secara komprehensif.

### Formulasi Matematis

Diberikan sebuah kelas PHP $C$ dengan kumpulan method $M = \{m_1, m_2, \dots, m_n\}$:

1. **Cyclomatic Complexity ($CC$) per Method**:
   $$CC(m) = 1 + \sum_{p \in P(m)} w(p)$$
   di mana $P(m)$ adalah himpunan titik percabangan dalam method $m$, dan $w(p)$ adalah bobot dari masing-masing jenis percabangan:
   - Percabangan biner standar (`if`, `elseif`, `else`): bobot 1.
   - Perulangan (`for`, `foreach`, `while`, `do`): bobot 1.
   - Operator logika (`&&`, `||`): bobot 1.
   - Percabangan multi-arah (`case` dalam `switch`, match arm dalam `match`): bobot 1.
   - Operator ternary (`? ... :`): bobot 1.
   - Null-coalescing operator (`??`): bobot 1.
   - Catch block (`catch`): bobot 1.

2. **Rasio Kerapatan Keputusan ($DD$)**:
   $$DD(C) = \frac{\sum_{m \in M} (CC(m) - 1)}{\max(1, \text{LLOC}(C))}$$

## 2. Diferensiasi Token Sintaksis PHP 8

Dalam PHP 8, karakter tanda tanya (`?`) memiliki peran ganda yang sangat berbeda secara semantik:
- **Nullable Type Hint** (misal: `public ?string $name = null`): Ini adalah deklarasi tipe pada properti atau parameter method, BUKAN titik percabangan logika.
- **Ternary Operator** (misal: `$val = $condition ? $a : $b`): Ini adalah titik percabangan kondisional yang menghasilkan alur eksekusi baru.

Kelas `DecisionDensityCounter` menggunakan stateful token parsing:
- Ketika menemukan karakter literal `?`, parser memeriksa token sebelum dan sesudahnya dalam AST token stream.
- Jika berada di dalam deklarasi parameter method atau deklarasi tipe pengembalian (return type) atau properti kelas, token tersebut diklasifikasikan sebagai `TYPE_NULLABLE` dan TIDAK menambah skor cyclomatic complexity.
- Hanya jika karakter `?` muncul dalam ekspresi logika di dalam tubuh eksekusi method (statement), parser mencatatnya sebagai `OPERATOR_TERNARY` yang meningkatkan $CC$.

## 3. Sertifikat dan Verifikasi Integritas

Sertifikat `DecisionDensityCertificate` membungkus hasil audit per berkas:
- Menghitung rasio kelas anemik: kelas yang memiliki LOC signifikan (> 30 baris) namun total decision points adalah 0.
- Menolak berkas yang melebihi batas anemic threshold pada modul inti domain.
- `DecisionDensityCertificateVerifier` memverifikasi kesesuaian tanda tangan kriptografis sertifikat dan memastikan tidak ada DTO bervolume besar tanpa justifikasi bisnis.

