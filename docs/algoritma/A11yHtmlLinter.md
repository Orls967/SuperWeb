# Algoritma A3: A11yHtmlLinter

**Kelas:** `Modules\Shared\Domain\Algorithms\Ui\A11yHtmlLinter`  
**Paket:** Shared Domain Algorithms (Aksesibilitas UI)  
**Sertifikat:** Dilengkapi verifier integritas `A11yLintCertificateVerifier`  
**Standar Acuan:** W3C Web Content Accessibility Guidelines (WCAG) 2.1 Level A & AA  

---

## 1. Intuisi & Latar Belakang

Pengguna disabilitas netra dan pengguna pembaca layar (*screen reader* seperti NVDA, JAWS, VoiceOver) bergantung pada struktur dokumen HTML yang semantik. Beberapa kesalahan umum dalam pengembangan web yang secara drastis menghancurkan aksesibilitas adalah:
1. Gambar tanpa teks alternatif (`alt`), menyebabkan pembaca layar membacakan URL berkas mentah.
2. Form input tanpa label deskriptif, membuat pengguna tidak mengetahui data apa yang harus diisikan.
3. Tombol aksi kosong tanpa teks dan tanpa `aria-label`, terdengar hanya sebagai "button" tanpa maksud.
4. Tautan tanpa teks dan tanpa `aria-label`, terdengar hanya sebagai "link".
5. Penggunaan atribut `tabindex` bernilai bilangan bulat positif ($> 0$), yang merusak alur fokus alami dokumen dan membuat penelusuran keyboard melompat secara tak terduga.

Algoritma A3 dirancang untuk mem-parse dokumen HTML / potongan Blade yang dirender, menelusuri pohon DOM secara teratur, mendeteksi pelanggaran aksesibilitas tersebut, dan memetakan pelanggaran tepat pada nomor baris sumber kode.

---

## 2. Aturan Pemeriksaan (Rulesets)

Algoritma A3 menerapkan 5 aturan deteksi formal:

1. **`IMG_ALT` (WCAG 2.1 SC 1.1.1 Non-text Content):**
   - Elemen `<img>` wajib memiliki atribut `alt`.
   - Atribut kosong `alt=""` diizinkan bila gambar dimaksudkan semata-mata sebagai elemen dekoratif.
   - Ketiadaan atribut `alt` dinyatakan sebagai pelanggaran.
2. **`INPUT_LABEL` (WCAG 2.1 SC 1.3.1 Info and Relationships & SC 4.1.2 Name, Role, Value):**
   - Berlaku untuk elemen `<input>`, `<select>`, dan `<textarea>`.
   - Dikecualikan untuk jenis input: `type="hidden"`, `type="submit"`, `type="button"`, `type="reset"`, dan `type="image"`.
   - Elemen dinyatakan valid jika memenuhi salah satu dari tiga syarat:
     a. Memiliki atribut `aria-label` yang tidak kosong.
     b. Memiliki atribut `aria-labelledby` yang merujuk elemen ID yang valid.
     c. Memiliki atribut `id` yang berpasangan dengan elemen `<label for="{id}">` di dalam dokumen.
   - Bila tidak memenuhi salah satu syarat di atas $\implies$ pelanggaran `INPUT_LABEL`.
3. **`BUTTON_NAME` (WCAG 2.1 SC 4.1.2 Name, Role, Value):**
   - Tag `<button>` wajib memiliki nama yang dapat diakses (*accessible name*).
   - Valid jika memuat teks konten (*inner text*) yang tidak kosong, atribut `aria-label`, atribut `aria-labelledby`, atau atribut `title`.
   - Tombol kosong tanpa nama aksesibel $\implies$ pelanggaran `BUTTON_NAME`.
4. **`LINK_NAME` (WCAG 2.1 SC 2.4.4 Link Purpose in Context):**
   - Tag `<a href="...">` wajib memiliki nama yang dapat diakses.
   - Valid jika memuat teks konten, gambar dengan atribut `alt`, atribut `aria-label`, atau atribut `aria-labelledby`.
   - Tautan kosong tanpa teks penjelas $\implies$ pelanggaran `LINK_NAME`.
5. **`TABINDEX_POSITIVE` (WCAG 2.1 SC 2.4.3 Focus Order):**
   - Atribut `tabindex` hanya boleh bernilai `0` (elemen dapat menerima fokus sesuai urutan tab alami) atau `-1` (elemen dapat menerima fokus secara programatik via JavaScript).
   - Nilai integer positif ($> 0$, misal `tabindex="1"`, `tabindex="2"`) merusak urutan tab alami $\implies$ pelanggaran `TABINDEX_POSITIVE`.

---

## 3. Vektor Uji Mandatori (Prompt Input & Expected Output)

### HTML Masukan Uji:
```html
<main>
  <img src="a.png">
  <img src="b.png" alt="">
  <label for="n">Nama</label><input id="n" type="text">
  <input type="text" name="q">
  <input type="hidden" name="t">
  <button></button>
  <button aria-label="Tutup"></button>
  <div tabindex="2"></div>
  <a href="/x"></a>
  <select id="s"></select>
</main>
```

### Hasil Analisis Langkah demi Langkah:
- **Baris 2:** `<img src="a.png">` tidak memiliki atribut `alt` $\implies$ **(Baris 2, IMG_ALT)**.
- **Baris 3:** `<img src="b.png" alt="">` memiliki atribut `alt=""` $\implies$ Lolos (dekoratif).
- **Baris 4:** `<label for="n">Nama</label><input id="n" type="text">` terhubung lewat atribut `for="n"` dan `id="n"` $\implies$ Lolos.
- **Baris 5:** `<input type="text" name="q">` tidak memiliki `aria-label` dan tidak ada label penunjuk $\implies$ **(Baris 5, INPUT_LABEL)**.
- **Baris 6:** `<input type="hidden" name="t">` adalah tipe hidden $\implies$ Lolos pengecualian.
- **Baris 7:** `<button></button>` kosong tanpa teks konten atau `aria-label` $\implies$ **(Baris 7, BUTTON_NAME)**.
- **Baris 8:** `<button aria-label="Tutup"></button>` memiliki `aria-label` valid $\implies$ Lolos.
- **Baris 9:** `<div tabindex="2"></div>` memiliki nilai tabindex positif $2 > 0$ $\implies$ **(Baris 9, TABINDEX_POSITIVE)**.
- **Baris 10:** `<a href="/x"></a>` tidak memiliki teks atau atribut nama $\implies$ **(Baris 10, LINK_NAME)**.
- **Baris 11:** `<select id="s"></select>` tidak memiliki label atau penanda `aria-label` $\implies$ **(Baris 11, INPUT_LABEL)**.

### Vektor Keluaran Terurut:
$$
[(2, \text{IMG\_ALT}), (5, \text{INPUT\_LABEL}), (7, \text{BUTTON\_NAME}), (9, \text{TABINDEX\_POSITIVE}), (10, \text{LINK\_NAME}), (11, \text{INPUT\_LABEL})]
$$
Dokumen yang bersih dari seluruh pelanggaran mengembalikan array kosong `[]`.

---

## 4. Karakteristik Kompleksitas & Implementasi PHP

- **Kompleksitas Waktu:** $\mathcal{O}(N)$ terhadap jumlah karakter dan node elemen dokumen HTML. Pemindaian DOM menggunakan `DOMDocument` standar PHP.
- **Kompleksitas Ruang:** $\mathcal{O}(N)$ untuk representasi pohon DOM dalam memori.
- **Determinisme:** Pelanggaran diurutkan berdasarkan nomor baris awal elemen secara menaik, lalu berdasarkan nama aturan.
