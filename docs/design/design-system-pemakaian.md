# Panduan Pemakaian Design System & Komponen Kanonikal `x-ds.*`

**Dokumen:** `docs/design/design-system-pemakaian.md`  
**Sasaran Pembaca:** Seluruh Pengembang Modul & Pelaksana Gelombang 8–25 (W8-P3 s/d P10)  
**Status:** Wajib Dipatuhi (*Mandatory Engineering Standard*)  

---

## 1. Prinsip Utama Penggunaan Komponen

Semua halaman antarmuka pengguna di SuperWeb wajib mengikuti tiga kaidah mutlak:
1. **Hanya Gunakan Komponen Kanonikal `x-ds.*`:** Dilarang membuat tag form mentah (`<input>`, `<select>`, `<button>`, `<table>`) secara terisolasi tanpa pembungkus komponen kanonikal.
2. **Larangan Keras Warna Hex Mentah (*No Hard-Coded Hex Colors*):** Dilarang menuliskan kode warna heksadesimal (misal: `class="text-[#1D4ED8]"`, `style="background: #ffffff"`) di dalam berkas Blade template atau stylesheet lokal modul. Semua pewarnaan wajib merujuk pada kelas token semantik Tailwind yang didukung variabel CSS desain sistem.
3. **Kepatuhan Responsif Mobile-First 375 px:** Setiap halaman dashboard, daftar data, maupun formulir transaksi wajib tertata rapi pada viewport 375 px tanpa menimbulkan scroll horizontal (`overflow-x`).

---

## 2. Alur Pengajuan Komponen Baru (*Component Release Governance*)

Jika suatu modul bisnis membutuhkan pola antarmuka baru yang belum tersedia di pustaka `x-ds.*`:
1. **Dilarang Melakukan Custom Fork Tanpa Izin:** Modul dilarang menyalin komponen `x-ds.*` lalu memodifikasinya di direktori lokal modul.
2. **Ajukan Permohonan Komponen Baru:**
   - Masuk ke menu `/design-system/releases` (atau gunakan API internal `ComponentReleaseAction::submitProposal()`).
   - Isi atribut wajib: `component_name` (kebab-case), `requested_by_module` (nama modul, misal `Hospital`), dan `reason` (alasan kebutuhan bisnis).
   - Status awal komponen tercatat sebagai `proposed`.
3. **Penyusunan Desain & Verifikasi Teknis (`in_design` $\rightarrow$ `a11y_review`):**
   - Komponen dirancang dengan kelas token semantik.
   - Perintah `php artisan ui:lint --paths=modules/Shared/resources/views/components/ds/{nama}.blade.php` wajib menghasilkan 0 pelanggaran.
   - Pasangan token kontras diuji via A1 `WcagContrastCalculator` dan wajib lolos Level AA ($45.000$ bps) pada tema `light`, `dark`, dan `hc`.
4. **Review & Approval Aksesibilitas (`a11y_review` $\rightarrow$ `approved`):**
   - Tim `design_system_owner` memvalidasi kelayakan penelusuran keyboard dan atribut pembaca layar.
   - Seluruh temuan pada `core_ds_a11y_findings` wajib terselesaikan (`is_remediated = true`).
   - Persetujuan resmi diterbitkan melalui `ApprovalEngine`.
5. **Rilis Publik (`approved` $\rightarrow$ `released`):**
   - Komponen terbit di katalog `/design-system` beserta contoh interaktif dan dokumentasi props.
   - Komponen siap digunakan oleh seluruh 30 lini bisnis.

---

## 3. Katalog Komponen Kanonikal & Sintaks Pemanggilan

### 3.1. Tombol (`x-ds.button`)
```blade
{{-- Varian Primer --}}
<x-ds.button variant="primary" type="submit">
    Simpan Transaksi
</x-ds.button>

{{-- Varian Sekunder / Batal --}}
<x-ds.button variant="secondary" href="/dashboard">
    Kembali
</x-ds.button>

{{-- Varian Bahaya dengan Indikator Loading --}}
<x-ds.button variant="danger" :loading="$isProcessing" aria-label="Hapus Dokumen">
    Hapus Data
</x-ds.button>
```

### 3.2. Form Input Teks & Moneter (`x-ds.input`, `x-ds.money-input`)
```blade
{{-- Input Teks dengan Label Otomatis dan Validasi Error Server --}}
<x-ds.input 
    name="patient_name" 
    label="Nama Lengkap Pasien" 
    placeholder="Masukkan nama sesuai KTP" 
    required 
/>

{{-- Input Moneter Rupiah Integer (Otomatis Pemisah Ribuan) --}}
<x-ds.money-input 
    name="deposit_amount" 
    label="Nominal Deposit Rawat Inap (IDR)" 
    :value="old('deposit_amount', 500000)" 
    required 
/>
```

### 3.3. Tabel Enterprise Responsif (`x-ds.table`)
Komponen tabel mendukung pengurutan kolom, penyaringan parameter URL, pagination, dan otomatis bertransformasi menjadi susunan kartu pada layar ponsel ($\le 375$ px).
```blade
<x-ds.table 
    :headers="[
        ['key' => 'code', 'label' => 'Kode Transaksi', 'sortable' => true],
        ['key' => 'date', 'label' => 'Tanggal', 'sortable' => true],
        ['key' => 'amount', 'label' => 'Total Nilai (IDR)', 'align' => 'right', 'tabular' => true],
        ['key' => 'status', 'label' => 'Status Operasional'],
        ['key' => 'actions', 'label' => 'Aksi', 'align' => 'right']
    ]"
    :rows="$records"
    :paginator="$records"
    exportable
    export-url="/transaksi/export"
>
    @scope('row', $row)
        <td>{{ $row->code }}</td>
        <td>{{ $row->formatted_date }}</td>
        <td class="text-right tabular-nums">{{ $row->formatted_amount }}</td>
        <td><x-ds.badge-status :status="$row->status" /></td>
        <td class="text-right">
            <x-ds.button variant="ghost" size="sm" href="/transaksi/{{ $row->id }}">Detail</x-ds.button>
        </td>
    @endscope
</x-ds.table>
```

### 3.4. Kartu Metrik KPI & Grafik Ringkas (`x-ds.stat`, `x-ds.sparkline`)
```blade
<x-ds.stat 
    title="Pendapatan Harian" 
    value="Rp 128.500.000" 
    delta="+14.2%" 
    delta-direction="up" 
    period="dibanding kemarin"
>
    <x-slot name="chart">
        <x-ds.sparkline :data="[12, 18, 15, 24, 28, 22, 35]" color="primary" />
    </x-slot>
</x-ds.stat>
```

### 3.5. Modal Konfirmasi Dua Langkah (`x-ds.modal-confirm`)
```blade
<x-ds.modal-confirm 
    id="modal-batalkan-pesanan" 
    title="Konfirmasi Pembatalan Pesanan" 
    confirm-text="Ya, Batalkan Pesanan" 
    action="/pesanan/123/batal" 
    method="POST"
>
    Apakah Anda yakin ingin membatalkan pengiriman ini? Tindakan ini akan mengembalikan alokasi armada ke status tersedia.
</x-ds.modal-confirm>
```

### 3.6. Notifikasi Melayang Berbasis Aksesibilitas (`x-ds.toast`)
Komponen toast kanonikal menggunakan atribut `role="alert"` dan `aria-live="polite"` agar pemberitahuan status terbaca oleh assistive technology tanpa menginterupsi pengguna:
```blade
<x-ds.toast 
    type="success" 
    title="Persetujuan Berhasil" 
    message="Komponen baru telah disetujui dan dialihkan ke antrean rilis." 
    :duration="5000" 
/>
```

### 3.7. Penampil State Kosong Terstruktur (`x-ds.empty`)
Digunakan pada tabel atau wadah data ketika hasil query menghasilkan nol rekaman:
```blade
<x-ds.empty 
    title="Belum Ada Usulan Komponen" 
    description="Seluruh lini bisnis saat ini telah menggunakan komponen kanonikal yang tersedia." 
    icon="cube"
>
    <x-slot name="action">
        <x-ds.button variant="primary" href="/design-system/releases/create">
            Ajukan Komponen Baru
        </x-ds.button>
    </x-slot>
</x-ds.empty>
```

### 3.8. Indikator Loading Denyut Asinkron (`x-ds.skeleton`)
Menampilkan placeholder visual saat data sedang dimuat secara asinkron dari backend:
```blade
{{-- Skeleton untuk Tabel 5 Baris --}}
<x-ds.skeleton type="table" :rows="5" :columns="4" />

{{-- Skeleton untuk Kartu Metrik --}}
<x-ds.skeleton type="card" />
```

### 3.9. Penunjuk Hierarki Rute Dinamis (`x-ds.breadcrumb`)
Menyediakan jejak navigasi terstruktur dengan markup schema list item:
```blade
<x-ds.breadcrumb :items="[
    ['label' => 'Beranda', 'url' => '/dashboard'],
    ['label' => 'Design System', 'url' => '/design-system'],
    ['label' => 'Katalog Komponen', 'url' => '/design-system/components'],
    ['label' => 'Tabel Enterprise', 'url' => null]
]" />
```

### 3.10. Visualisasi Tren Data Garis & Batang SVG Native (`x-ds.chart-line`, `x-ds.chart-bar`)
Dihasilkan murni di server menggunakan geometri matematika PHP tanpa dependensi pustaka JavaScript pihak ketiga:
```blade
<x-ds.chart-line 
    :labels="['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun']" 
    :values="[45, 52, 58, 64, 72, 85]" 
    height="220" 
    stroke-color="primary" 
/>

<x-ds.chart-bar 
    :labels="['Logistik', 'RS', 'Aviasi', 'Retail', 'Manufaktur']" 
    :values="[120, 95, 140, 80, 110]" 
    height="220" 
    bar-color="primary" 
/>
```

### 3.11. Linimasa Riwayat Audit & Status (`x-ds.timeline`)
Menampilkan jejak peristiwa historis secara berurutan lengkap dengan stempel waktu, nama aktor, peran, dan ringkasan mutasi data:
```blade
<x-ds.timeline :events="$componentHistory" />
```

### 3.12. Bilah Filter & Toolbar Ekspor (`x-ds.filter-bar`, `x-ds.export-toolbar`)
```blade
<x-ds.filter-bar 
    :filters="[
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => $statusOptions],
        ['name' => 'module', 'label' => 'Modul', 'type' => 'text', 'placeholder' => 'Cari modul...']
    ]" 
/>

<x-ds.export-toolbar 
    csv-url="/design-system/export/csv" 
    excel-url="/design-system/export/excel" 
    pdf-url="/design-system/export/pdf" 
/>
```

---

## 4. Matriks Aksesibilitas Komponen (WCAG 2.1 Level AA)

| Nama Komponen | Keyboard Navigation | Kontras Warna AA | Screen Reader Label | State Management |
|---|---|---|---|---|
| `x-ds.button` | Enter, Space | $\ge 4.5:1$ (45.000 bps) | `aria-label`, `aria-busy` | Normal, Hover, Focus, Disabled, Loading |
| `x-ds.input` | Tab, Shift+Tab | $\ge 4.5:1$ teks & placeholder | `id` + `<label for>`, `aria-invalid` | Default, Focus-visible, Error, Disabled |
| `x-ds.select` | Tab, Panah Atas/Bawah | $\ge 4.5:1$ opsi | `aria-haspopup="listbox"` | Expanded, Collapsed, Selected |
| `x-ds.combobox` | Panah, Enter, Esc | $\ge 4.5:1$ opsi | `role="combobox"`, `aria-expanded` | Filtered, Active item focus |
| `x-ds.table` | Tab, Panah sel | $\ge 4.5:1$ teks sel | `role="table"`, `aria-sort` | Sorting, Filtered, Pagination, Card-mode |
| `x-ds.modal-confirm` | Esc menutup, Tab trap | $\ge 4.5:1$ modal body | `role="dialog"`, `aria-modal="true"` | Open, Closing, Double-submit locked |
| `x-ds.toast` | Esc menutup | $\ge 4.5:1$ toast card | `role="alert"`, `aria-live="polite"` | Auto-dismiss, Dismissed |
| `x-ds.tabs` | Panah Kiri/Kanan | $\ge 4.5:1$ tab label | `role="tablist"`, `aria-selected` | Active tab, Inactive tab |

---

## 4. Variabel Token Desain & Tema Tri-State

Semua kelas warna merujuk pada variabel CSS standar berikut yang didefinisikan pada `:root` dan diseleksi via `[data-theme="light"]`, `[data-theme="dark"]`, dan `[data-theme="hc"]`:

| Nama Variabel CSS | Tema `light` | Tema `dark` | Tema `hc` (Kontras Tinggi) |
|---|---|---|---|
| `--color-bg` | `#FFFFFF` | `#0F172A` | `#000000` |
| `--color-surface` | `#F8FAFC` | `#1E293B` | `#000000` (border kontras) |
| `--color-text` | `#111827` | `#F1F5F9` | `#FFFFFF` |
| `--color-text-muted` | `#6B7280` | `#94A3B8` | `#FFFFFF` |
| `--color-primary` | `#1D4ED8` | `#93C5FD` | `#FFFF00` / `#93C5FD` |
| `--color-border` | `#E2E8F0` | `#334155` | `#FFFFFF` |
| `--color-danger` | `#B91C1C` | `#EF4444` | `#FF0000` |

---

## 5. Pemeriksaan Mandiri Pra-Commit (*Pre-Commit Linting*)

Sebelum mengajukan Pull Request, setiap pengembang wajib menjalankan linter antarmuka pengguna:
```bash
# Periksa file Blade modul yang diubah
php artisan ui:lint --paths=modules/Hospital/resources/views/

# Periksa render halaman pada rute tertentu
php artisan ui:lint --render=/hospital/patients

# Periksa kepatuhan struktur responsif 375 px
php artisan ui:lint --responsive
```
Kriteria keberhasilan adalah **0 pelanggaran kontras dan 0 pelanggaran label**.
