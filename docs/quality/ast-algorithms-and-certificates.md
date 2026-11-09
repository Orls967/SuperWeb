# Spesifikasi Algoritma Kualitas AST & Sertifikat Kriptografis (A1, A2, A3)

## 1. Pendahuluan
Kualitas basis kode dalam proyek enterprise multi-modul tidak boleh hanya dinilai dari metrik dangkal (seperti sekadar jumlah baris mentah LOC atau linting visual Pint). Kode dapat dengan mudah dimanipulasi melalui teknik "padding" (penggelembungan artifisial tanpa substansi).

Untuk menegakkan integritas standar W8, arsitektur `app/Quality/Algorithms` mengimplementasikan tiga algoritma bersertifikat matematis:
1. **A1 `DuplicateWindowDetector`**: Algoritma Rabin-Karp berbasis polynomial rolling hash untuk mendeteksi blok duplikasi token (M-4).
2. **A2 `DecisionDensityCounter`**: Parser stream token untuk mengukur kepadatan percabangan logika dan mengidentifikasi DTO anemik tanpa komputasi (M-3).
3. **A3 `ClassReachabilityGraph`**: Parser graf berarah dependensi untuk mendeteksi kelas tak terjangkau / yatim piatu (M-5).

---

## 2. Algoritma A1: DuplicateWindowDetector (Rabin-Karp Rolling Hash)

### Dasar Teori & Formulasi Matematika:
Diberikan barisan token ternormalisasi $T = \langle t_0, t_1, \dots, t_{N-1} \rangle$ dan ukuran jendela geser (sliding window) $W$.
Setiap token $t_i$ dipetakan ke nilai integer non-negatif $v(t_i) = \text{crc32}(t_i) \pmod M$.
Hash jendela $k$-elemen dari indeks $i$ hingga $i+W-1$ didefinisikan menggunakan skema Horner:

$$H_i = \sum_{j=0}^{W-1} v(t_{i+j}) \cdot B^{W-1-j} \pmod M$$

di mana:
- Basis polynomial $B = 257$ (bilangan prima lebih besar dari ukuran alfabet token dasar).
- Modulo prima besar $M = 1\,000\,000\,007$ atau $2\,147\,483\,647$.

### Pergeseran Jendela dalam Waktu Konstan $O(1)$:
Ketika jendela bergeser satu posisi dari indeks $i$ ke $i+1$, nilai hash baru dihitung dari hash lama tanpa perlu mengulang penjumlahan:

$$H_{i+1} = \left( (H_i - v(t_i) \cdot B^{W-1}) \cdot B + v(t_{i+W}) \right) \pmod M$$

Nilai $B^{W-1} \pmod M$ dipra-komputasi sekali di awal dengan kompleksitas $O(W)$.

### Normalisasi Token Anti-Evasion:
Sebelum hash dihitung, stream token dinormalisasi untuk mencegah penghindaran deteksi melalui modifikasi semu:
1. Whitespace (`T_WHITESPACE`), komentar baris (`T_COMMENT`), dan komentar blok (`T_DOC_COMMENT`) dibuang sepenuhnya.
2. Nama variabel (`T_VARIABLE`) diganti menjadi token kanonikal tunggal `$v`.
3. String konstan (`T_CONSTANT_ENCAPSED_STRING`) diganti menjadi `S`.
4. Angka konstan (`T_LNUMBER`, `T_DNUMBER`) diganti menjadi `N`.

### Eliminasi False Positive (Verifikasi Tabrakan):
Jika ditemukan dua jendela berbeda dengan hash yang sama ($H_a = H_b$), algoritma melakukan perbandingan token demi token secara langsung. Hanya pasangan yang lolos verifikasi identik 100% yang dicatat sebagai blok duplikasi nyata.

---

## 3. Algoritma A2: DecisionDensityCounter (Cyclomatic Branching Ratio)

### Konsep Kepadatan Keputusan:
Cyclomatic complexity tradisional hanya menghitung jumlah jalur independen dalam fungsi. Namun, suatu kelas dengan 500 baris kode yang hanya memuat 1 percabangan memiliki kepadatan logika yang sangat rendah dibandingkan fungsi 30 baris dengan 5 percabangan.

Algoritma A2 mengukur rasio percabangan keputusan eksekutabel terhadap jumlah statement:

$$\text{DecisionDensity} = \frac{\text{DecisionPoints}}{\max(1, \text{ExecutableStatements})}$$

### Pemetaan Token Percabangan (`DecisionPoints`):
Setiap kemunculan token berikut bernilai $+1$:
- Struktur kontrol: `T_IF`, `T_ELSEIF`, `T_FOR`, `T_FOREACH`, `T_WHILE`, `T_CASE`, `T_CATCH`.
- Operator logika kondisional: `T_BOOLEAN_AND` (`&&`), `T_BOOLEAN_OR` (`||`), `T_COALESCE` (`??`).
- Operator ternary: `?` (dikecualikan dari tanda nullable type-hint `?string`).

### Deteksi Struktur Data Anemik (M-3):
Kelas dikategorikan sebagai anemik / pasif jika:
$$\text{DecisionPoints} = 0 \quad \text{DAN} \quad (\text{PropertyCount} \ge 3 \ \lor \ \text{MethodCount} = 0)$$
Jika rasio kelas anemik dalam suatu direktori melebihi $20\%$ dari total kelas, gerbang kualitas T-10 menolak komit dengan kode error `M-3`.

---

## 4. Algoritma A3: ClassReachabilityGraph (Directed Reachability Traversal)

### Konstruksi Graf Berarah:
Didefinisikan graf berarah $G = (V, E)$ di mana:
- Simpul $V$ merepresentasikan himpunan seluruh kelas, antarmuka, trait, dan enum PHP yang dideklarasikan.
- Busur $(u, v) \in E$ merepresentasikan bahwa kelas $u$ memiliki dependensi eksplisit terhadap kelas $v$ (melalui pernyataan `use`, injeksi parameter konstruktor, pewarisan `extends`, implementasi `implements`, atau instansiasi langsung `new`).

### Himpunan Akar (Root Set $R$):
Titik masuk eksekusi resmi sistem didefinisikan sebagai himpunan akar:
$$R = \{ v \in V \mid v \text{ adalah Controller, Command, ServiceProvider, Listener, atau Test} \}$$

### Penelusuran Keterjangkauan (BFS Traversal):
Algoritma menjalankan penelusuran Breadth-First Search dimulai dari seluruh simpul di $R$. Himpunan simpul terjangkau $V_{\text{reachable}}$ diperoleh dari penutupan transitif:
$$V_{\text{reachable}} = \{ v \in V \mid \exists r \in R, \ r \rightsquigarrow v \}$$

### Deteksi Kelas Yatim (Orphan Nodes / M-5):
Himpunan kelas yatim piatu $V_{\text{orphan}}$ adalah simpul yang tidak dapat dicapai dari akar mana pun:
$$V_{\text{orphan}} = V \setminus V_{\text{reachable}}$$

Jika ditemukan setidaknya 1 kelas yatim ($|V_{\text{orphan}}| > 0$), gerbang T-10 menolak komit dengan pelanggaran `M-5`.

---

## 5. Sertifikat Kriptografis HMAC-SHA256
Setiap hasil eksekusi algoritma A1, A2, dan A3 memancarkan sertifikat digital yang memuat:
- Hash input sumber yang dianalisis.
- Nilai kuantitatif metrik (token count, ratio, nodes, edges).
- Tanda tangan digital HMAC-SHA256 menggunakan rahasia sistem internal.

Kelas `CertificateVerifier` masing-masing memverifikasi keutuhan sertifikat sebelum nilai metrik dicatat ke tabel persisten `plt_phase_metrics`. Pemalsuan nilai metrik akan langsung menyebabkan kegagalan audit platform dua sumber (`platform:audit`).

