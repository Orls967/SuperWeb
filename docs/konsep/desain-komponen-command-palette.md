# Spesifikasi Komponen UI: Enterprise Command Palette & Modal Pencarian (Ctrl+K)

**Fase:** 194 — Platform: Accessible Command Palette Component Specification  
**Komponen Blade:** `resources/views/components/enterprise/command-palette.blade.php`  
**Pola Aksesibilitas:** W3C WAI-ARIA 1.2 Combobox with Listbox Popup

---

## 1. Desain Interaksi & Perilaku Pengguna

Command Palette berfungsi sebagai pintu gerbang interaksi cepat bagi operator dan pengguna enterprise.

### Alur Kerja Interaksi:
1. **Pemicu Pembukaan (Activation Trigger):**  
   Pengguna menekan pintasan `Ctrl + K` (Windows/Linux) atau `Cmd + K` (macOS), atau mengklik tombol pencarian pada bilah navigasi atas.
2. **Penguncian Fokus (Focus Trap):**  
   Fokus keyboard secara otomatis dialihkan ke elemen input teks pencarian (`<input role="combobox">`). Fokus tidak dapat keluar dari modal selama modal terbuka (kebal terhadap kebocoran tab per WCAG 2.1.2).
3. **Pencarian Seketika Ber-Debounce (Live Search):**  
   Input pengguna dievaluasi dengan debounce 200ms. Kueri dikirimkan ke endpoint `/api/global-search` secara asinkron (menggunakan `fetch` native tanpa dependensi eksternal).
4. **Navigasi Tombol Panah (Arrow Key Navigation):**  
   Pengguna dapat menavigasi daftar hasil pencarian menggunakan tombol panah atas (`ArrowUp`) dan panah bawah (`ArrowDown`). Atribut `aria-activedescendant` diperbarui secara dinamis menunjuk ke id item yang sedang disorot.
5. **Pemilihan & Aksi (Selection & Dispatch):**  
   Menekan tombol `Enter` membuka tautan entitas yang sedang disorot. Menekan tombol `Escape` menutup modal dan mengembalikan fokus keyboard ke elemen pemantik sebelumnya.

---

## 2. Struktur Aksesibilitas WAI-ARIA

```html
<div role="dialog" aria-modal="true" aria-labelledby="command-palette-title" class="...">
  <div class="search-input-wrapper">
    <input type="text"
           role="combobox"
           aria-expanded="true"
           aria-autocomplete="list"
           aria-controls="command-palette-results"
           aria-activedescendant="result-item-2"
           placeholder="Ketik kata kunci untuk mencari transaksi, menu, aset..." />
  </div>
  
  <ul id="command-palette-results" role="listbox" aria-label="Hasil Pencarian">
    <li id="result-item-1" role="option" aria-selected="false" class="...">
      <span>Work Order #WO-001 (Honda CR-V)</span>
    </li>
    <li id="result-item-2" role="option" aria-selected="true" class="bg-indigo-600 text-white ...">
      <span>Surat Jalan Pengiriman #SJ-8890</span>
    </li>
  </ul>
</div>
```

---

## 3. Gaya Visual & Dukungan Multi-Tema Kanonikal

Komponen Command Palette sepenuhnya menggunakan token CSS kanonikal `x-ds.*`:
- **Mode Terang (Light):** Latar belakang modal menggunakan `--color-bg-surface: #FFFFFF` dengan bayangan elevasi tinggi `shadow-2xl` dan border `--color-border-default: #E2E8F0`.
- **Mode Gelap (Dark):** Latar belakang menggunakan `--color-bg-surface: #0F172A` dengan border halus `--color-border-default: #1E293B`.
- **Mode Kontras Tinggi (High Contrast):** Latar belakang murni hitam `#000000` dengan garis batas tebal 2px solid putih `#FFFFFF` dan seleksi item berwarna kuning kontras `#FFFF00`.

