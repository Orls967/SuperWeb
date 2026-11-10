# Algoritma A2: ThemeTokenResolver

**Kelas:** `Modules\Shared\Domain\Algorithms\Ui\ThemeTokenResolver`  
**Paket:** Shared Domain Algorithms (Aksesibilitas UI)  
**Sertifikat:** Dilengkapi verifier integritas `TokenResolutionCertificateVerifier`  

---

## 1. Intuisi & Latar Belakang

Sistem desain modern membutuhkan kemampuan pergantian tema dinamis:
1. `light` (Mode Terang): Standar perkantoran dengan latar putih/terang.
2. `dark` (Mode Gelap): Mengurangi kelelahan mata (*eye-strain*) pada kondisi pencahayaan redup.
3. `hc` (Mode Kontras Tinggi): Memaksimalkan keterbacaan untuk pengguna dengan gangguan penglihatan atau layar dengan pantulan sinar matahari luar ruangan.

Untuk menghindari duplikasi ratusan nilai token yang identik antar tema, sistem menerapkan konsep **Pewarisan Tema (*Theme Inheritance*)**:
$$
\text{hc} \longrightarrow \text{dark} \longrightarrow \text{light}
$$
Selain pewarisan, token dapat merujuk pada token lain menggunakan sintaks alias `{token_name}`. Aturan kunci dalam arsitektur ini adalah: **Alias selalu diselesaikan dalam konteks tema peminta (*requesting theme*)**, bukan pada tema tempat token didefinisikan. Mekanisme ini memungkinkan tema turunan mengubah token dasar (misal `text` menjadi `#FFFFFF` di tema `hc`) dan secara otomatis memperbarui seluruh token turunan yang mengacu pada `{text}`.

---

## 2. Formulasi Matematis & Logika Resolusi

### 2.1. Model Data Masukan
Diberikan himpunan definisi token per tema:
$$
\mathcal{T} = \{ (t, k, v) \mid t \in \{\text{light}, \text{dark}, \text{hc}\}, k \in \mathcal{K}, v \in \mathcal{V} \}
$$
di mana $k$ adalah kunci token dan $v$ dapat berupa nilai warna literal (misal: `#1D4ED8`) atau alias `{alias_kunci}`.

### 2.2. Prosedur Resolusi Token
Untuk mencari nilai token $k$ pada tema peminta $T_{req}$:
1. **Penelusuran Rantai Warisan:**
   Buat rantai penelusuran $\mathcal{C}(T_{req})$:
   - Jika $T_{req} = \text{hc}$, rantai adalah $[\text{hc}, \text{dark}, \text{light}]$.
   - Jika $T_{req} = \text{dark}$, rantai adalah $[\text{dark}, \text{light}]$.
   - Jika $T_{req} = \text{light}$, rantai adalah $[\text{light}]$.
2. Temukan entri pertama $(t, k, v)$ di mana $t \in \mathcal{C}(T_{req})$. Jika tidak ditemukan untuk semua $t \in \mathcal{C}(T_{req})$, tandai sebagai `TOKEN_UNDEFINED`.
3. **Penyelesaian Alias Rekursif:**
   Jika nilai $v$ cocok dengan pola `^\{([a-zA-Z0-9_.-]+)\}$`:
   - Ambil kunci alias target $k' = \text{grup}(1)$.
   - Catat jalur penelusuran alias $\mathcal{P} = [k]$.
   - Jika $k' \in \mathcal{P}$, maka terdapat siklus alias $\implies$ tandai status sebagai `TOKEN_CYCLE`.
   - Lakukan resolusi rekursif untuk $k'$ **pada tema peminta** $T_{req}$.
4. **Verifikasi Pasangan Kontras Standar:**
   Setelah semua token pada tema $T_{req}$ terselesaikan, lakukan verifikasi rasio kontras warna terhadap pasangan semantik standar via Algoritma A1 (`WcagContrastCalculator`):
   - `text` terhadap `bg`
   - `onPrimary` terhadap `primary`
   - `muted` terhadap `bg`
   Setiap pasangan wajib diuji terhadap ambang batas WCAG AA ($45.000$ bps).

---

## 3. Contoh Hitungan Langkah demi Langkah (Vektor Prompt)

### Definisi Token Awal
- **`light`**:
  `bg` = `#FFFFFF`, `text` = `#111827`, `primary` = `#1D4ED8`, `onPrimary` = `{bg}`, `muted` = `#9CA3AF`, `danger` = `#B91C1C`.
- **`dark`**:
  `bg` = `#0F172A`, `text` = `#F1F5F9`, `primary` = `#93C5FD`, `muted` = `#94A3B8`.
- **`hc`**:
  `bg` = `#000000`, `text` = `#FFFFFF`, `muted` = `{text}`.

### Langkah Resolusi Tema `hc`
1. Resolusi `bg`: Didefinisikan langsung di `hc` $\implies$ `#000000`.
2. Resolusi `text`: Didefinisikan langsung di `hc` $\implies$ `#FFFFFF`.
3. Resolusi `primary`: Tidak ada di `hc`. Cari di `dark` $\implies$ ditemukan `#93C5FD`.
4. Resolusi `onPrimary`: Tidak ada di `hc` atau `dark`. Cari di `light` $\implies$ ditemukan `{bg}`.
   - Evaluasi `{bg}` pada tema peminta `hc` $\implies$ menghasilkan `#000000` (bukan `#FFFFFF` milik `light`).
5. Resolusi `muted`: Didefinisikan di `hc` sebagai `{text}`.
   - Evaluasi `{text}` pada tema peminta `hc` $\implies$ menghasilkan `#FFFFFF`.
6. Resolusi `danger`: Tidak ada di `hc` atau `dark`. Cari di `light` $\implies$ `#B91C1C`.

### Hasil Verifikasi Rasio Kontras Pasangan
1. **Tema `light`:**
   - `text` (`#111827`) vs `bg` (`#FFFFFF`): rasio $177.397$ bps ($17.7:1$) $\implies$ **LULUS AA**.
   - `onPrimary` (`#FFFFFF`) vs `primary` (`#1D4ED8`): rasio $67.016$ bps ($6.7:1$) $\implies$ **LULUS AA**.
   - `muted` (`#9CA3AF`) vs `bg` (`#FFFFFF`): rasio $25.388$ bps ($2.5:1$) $\implies$ **GAGAL AA** (terdeteksi sebagai peringatan).
2. **Tema `dark`:**
   - `text` (`#F1F5F9`) vs `bg` (`#0F172A`): rasio $162.960$ bps $\implies$ **LULUS AA**.
   - `onPrimary` (`#0F172A`) vs `primary` (`#93C5FD`): rasio $99.000$ bps $\implies$ **LULUS AA**.
   - `muted` (`#94A3B8`) vs `bg` (`#0F172A`): rasio $69.626$ bps $\implies$ **LULUS AA**.
3. **Tema `hc`:**
   - `text` (`#FFFFFF`) vs `bg` (`#000000`): rasio $210.000$ bps ($21:1$) $\implies$ **LULUS AA**.
   - `onPrimary` (`#000000`) vs `primary` (`#93C5FD`): rasio $116.454$ bps $\implies$ **LULUS AA**.
   - `muted` (`#FFFFFF`) vs `bg` (`#000000`): rasio $210.000$ bps $\implies$ **LULUS AA**.

---

## 4. Kompleksitas & Penanganan Kasus Tepi

- **Kompleksitas Waktu:** $\mathcal{O}(K \times D)$ di mana $K$ adalah total token dan $D \le 3$ kedalaman rantai tema. Karena himpunan kunci finite ($< 200$), runtime eksekusi $< 5$ ms.
- **Siklus Dependensi:** Deteksi $a \rightarrow b \rightarrow a$ menggunakan set penelusuran rekursif `visited`. Jika siklus terjadi, resolver mengembalikan flag `TOKEN_CYCLE` tanpa memicu stack overflow.
- **Token Tak Terdefinisi:** Alias merujuk nama tidak dikenal mengembalikan `TOKEN_UNDEFINED`.

