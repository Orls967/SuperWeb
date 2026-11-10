# Algoritma A3: MINSTD Pseudo-Random Generator & Cryptographic Fingerprint Chain

Dokumen ini mendokumentasikan spesifikasi teknis, rancangan matematis, sifat kriptografis, dan implementasi algoritma **A3 Stream Seed and Fingerprint** pada Kernel Simulasi Superweb.

---

## 1. Latar Belakang & Filosofi Rancangan

Simulasi skala perusahaan memerlukan dua elemen matematika yang saling melengkapi:
1. **Pembangkit Bilangan Acak Semu (PRNG) yang Cepat dan Deterministik:** Menghasilkan variasi bisnis sintetis (waktu kedatangan pasien, pilihan tipe kamar tamu, ukuran kontainer) tanpa bergantung pada fungsi acak sistem operasi (`rand()`, `mt_rand()`, atau `/dev/urandom` yang non-deterministik).
2. **Rantai Integritas Kriptografis (Cryptographic Hash Chain):** Mengikat seluruh evolusi keadaan dari hari pertama hingga hari terakhir ke dalam satu digest SHA-256 yang sensitif terhadap perubahan terkecil sekalipun (*avalanche effect*).

Algoritma A3 menggabungkan generator Lehmer LCG baku MINSTD dengan rantai hash berbasis JSON kanonikal.

---

## 2. Generator Lehmer LCG MINSTD Park-Miller

Algoritma A3 menggunakan Multiplicative Congruential Generator (MCG) yang dirumuskan oleh Stephen K. Park dan Keith W. Miller (1988, 1993):
$$X_{n+1} = (a \cdot X_n) \pmod{m}$$

### Parameter Baku:
- **Pengali ($a$):** $48.271$ (revisi Park-Miller 1993, memiliki sifat spektral yang lebih unggul dibandingkan pengali 16.807 asli).
- **Modulus ($m$):** $2^{31} - 1 = 2.147.483.647$ (bilangan prima Mersenne ke-8, $M_{31}$).
- **Domain Nilai Legal (Z_m^*):** $X \in [1, 2^{31} - 2]$. Nilai seed $0$ dilarang karena akan menghasilkan titik tetap $\forall n, X_n = 0$.

### Sifat Periode Penuh:
Karena modulus $m$ adalah bilangan prima Mersenne dan $a = 48.271$ merupakan akar primitif (*primitive root*) modulo $m$, barisan bilangan acak memiliki periode penuh:
$$P = m - 1 = 2.147.483.646 \text{ bilangan unik}$$
Sebelum barisan mulai berulang, generator mampu menghasilkan lebih dari dua miliar bilangan acak semu yang terdistribusi seragam secara statistik.

---

## 3. Pemisahan Aliran Deterministik (`split`)

Dalam simulasi multi-domain, penambahan event pada modul Hospital tidak boleh menggeser deret acak pada modul Logistics. Untuk mencapai isolasi domain, Algoritma A3 mengimplementasikan teknik pemisahan aliran (*stream splitting*):
$$\text{derived\_seed} = \left(\text{hexdec}(\text{substr}(\text{SHA256}(\text{"stream:"} \cdot \text{base\_seed} \cdot \text{":"} \cdot \text{stream\_name}), 0, 8)) \pmod{m - 1}\right) + 1$$

Dengan mekanisme ini:
- Aliran `hospital_admissions` memiliki sub-seed yang stabil.
- Aliran `hotel_bookings` memiliki sub-seed independen.
- Aliran `logistics_containers` memiliki sub-seed terisolasi.
- Setiap modul dapat memanggil generator secara bebas tanpa merusak urutan acak modul lain.

---

## 4. Rantai Fingerprint Hash SHA-256 (D34)

Integritas jalannya simulasi diamankan melalui rantai hash harian (*daily hash chain*). Rantai ini menghubungkan status hari ke-$i$ dengan status hari ke-$(i-1)$.

### Formula Matematis:
1. **Genesis Hash (Hari 0):**
   $$h_0 = \text{SHA256}(\text{"sim-run\|seed="} \cdot \text{seed})$$
2. **Transisi Hari ke-$i$ ($i \ge 1$):**
   $$h_i = \text{SHA256}(h_{i-1} \cdot \text{"\|"} \cdot \text{day} \cdot \text{"\|"} \cdot \text{canonicalJson}(\text{summary}_i))$$

### Aturan Kanonikalisasi JSON (RFC 8785 Compliant):
Untuk menjamin bahwa format serialisasi teks tidak berbeda antar platform, fungsi `canonicalJson` memberlakukan aturan ketat:
1. **Pengurutan Kunci Alfabetis:** Seluruh kunci array asosiatif diurutkan secara leksikografis menggunakan `ksort()`.
2. **Tanpa Karakter Escape Tambahan:** Menggunakan bendera `JSON_UNESCAPED_SLASHES` dan `JSON_UNESCAPED_UNICODE`.
3. **Format Angka Deterministik:** Bilangan bulat direpresentasikan tanpa desimal, bilangan pecahan tanpa trailing nol tak tentu.

Jika salah satu modul menghasilkan nilai selisih $1$ rupiah atau satu event ekstra, `canonicalJson` akan berubah, mengubah $h_i$, dan merambat ke seluruh hash hari-hari berikutnya.

---

## 5. Ringkasan Kompleksitas Asimtotik

| Fungsi | Kompleksitas Waktu | Kompleksitas Ruang | Keterangan |
|---|---|---|---|
| `nextSeed(current)` | $O(1)$ | $O(1)$ | Satu perkalian dan satu modulo 64-bit |
| `split(seed, name)` | $O(L)$ | $O(1)$ | $L$ adalah panjang nama aliran |
| `genesisFingerprint(seed)` | $O(1)$ | $O(1)$ | Hash SHA-256 dari string konstan |
| `nextFingerprint(prev, day, summary)` | $O(K \log K + S)$ | $O(S)$ | $K$ kunci summary, $S$ ukuran payload string |
| `verifyContinuity(seed, history)` | $O(D \cdot (K \log K + S))$ | $O(1)$ | $D$ hari rekaman riwayat simulasi |

---

## 6. Pembuktian Formal Resistensi Tabrakan (Collision Resistance)

**Teorema:** Jika fungsi kompresi SHA-256 memenuhi asumsi *Random Oracle Model* dengan ketahanan tabrakan (*collision resistance*) berbobot $2^{128}$ operasi, maka probabilitas adanya dua sesi simulasi berbeda yang menghasilkan hash rantai identik $h_i = h'_i$ pada hari ke-$i$ adalah negligible.

*Bukti:*
Misalkan terdapat dua riwayat simulasi berbeda:
$$H = (h_0, S_1, S_2, \dots, S_n) \quad \text{dan} \quad H' = (h'_0, S'_1, S'_2, \dots, S'_n)$$
dengan $H \neq H'$.
- Kasus 1 ($h_0 \neq h'_0$): Seed berbeda. Pada hari pertama:
  $$h_1 = \text{SHA256}(h_0 \cdot \text{"\|1\|"} \cdot \text{canon}(S_1)), \quad h'_1 = \text{SHA256}(h'_0 \cdot \text{"\|1\|"} \cdot \text{canon}(S'_1))$$
  Karena $h_0 \neq h'_0$, preimage SHA-256 berbeda. Peluang $h_1 = h'_1$ dibatasi oleh batas peluang tabrakan SHA-256, yaitu $\le \frac{1}{2^{256}}$.
- Kasus 2 ($h_0 = h'_0$ dan terdapat indeks terkecil $k$ di mana $S_k \neq S'_k$):
  Hingga hari $k-1$, $h_{k-1} = h'_{k-1}$. Pada hari $k$:
  Preimage $h_k$ adalah $h_{k-1} \cdot \text{"\|"} \cdot k \cdot \text{"\|"} \cdot \text{canon}(S_k)$ dan preimage $h'_k$ adalah $h_{k-1} \cdot \text{"\|"} \cdot k \cdot \text{"\|"} \cdot \text{canon}(S'_k)$.
  Karena $S_k \neq S'_k$, kanonikalisasi menghasilkan string teks yang berbeda. Peluang $h_k = h'_k$ adalah $\le 2^{-256}$.
- Dengan induksi pada setiap langkah hari, sifat acak kriptografis SHA-256 mencegah konvergensi dua rantai yang telah menyimpang. Dengan demikian, rantai fingerprint bersifat tahan tabrakan dan mengikat keadaan secara unik. $\blacksquare$

---

## 7. Rangkuman Pengujian & Verifikasi

Algoritma A3 diverifikasi melalui:
- **Vektor Uji MINSTD Prompt Resmi:**
  - Seed $42 \to$ nilai berikutnya persis $2.027.382$.
  - Seed $1 \to$ nilai berikutnya $48.271$.
- **Vektor Uji Split Stream:** Pemisahan nama stream `hospital` dan `hotel` menghasilkan sub-seed yang berbeda dan konsisten.
- **Vektor Uji Rantai Hash:** Verifikasi genesis seed $42$ menghasilkan hash spesifik, dan transisi summary hari $1$ menghasilkan digest identik pada setiap eksekusi.
- **Deteksi Korupsi Fixture:** Manipulasi satu karakter pada summary memicu `InvalidAlgorithmCertificateException`.

