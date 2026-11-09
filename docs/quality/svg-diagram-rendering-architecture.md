# Arsitektur Renderer SVG Murni PHP (docs:generate)

## 1. Latar Belakang dan Pembatasan

Sesuai dengan keputusan arsitektur (ADR-0237) dan larangan penambahan dependensi biner/Python eksternal (seperti Mermaid CLI via npm atau generator berbasis headless Chrome), modul Quality mengimplementasikan parser sintaks Mermaid dan renderer SVG murni dalam bahasa PHP (`App\Quality\Docs\Render\*`).

Renderer ini membaca berkas dokumentasi markdown (`.md`), mengekstrak blok kode dengan identifier ```mermaid, mem-parsing struktur graf atau state machine, dan merender berkas grafik vektor skalar (`.svg`) mandiri tanpa memerlukan subprocess eksternal.

## 2. Komponen Renderer

1. **`SvgCanvas`**:
   - Engine kanvas vektor dasar yang menyediakan API fluent untuk menggambar elemen primitif SVG: `<rect>`, `<circle>`, `<path>`, `<line>`, `<text>`, dan marker panah `<marker>`.
   - Menghitung bounding box secara otomatis untuk penyesuaian atribut `viewBox` dan `width`/`height`.
2. **`MermaidToSvgCompiler`**:
   - Router pengenal diagram: mendeteksi jenis diagram (`flowchart`, `graph`, `stateDiagram`, `sequenceDiagram`, `classDiagram`, `erDiagram`).
   - Meneruskan AST diagram ke spesifik sub-renderer.
3. **`StateMachineDiagramRenderer`**:
   - Menggambar lingkaran status awal/akhir, kotak rounded untuk status intermediat, dan kurva panah Bézier untuk busur transisi.
4. **`ArchitectureDiagramRenderer`**:
   - Mengatur layout grid hierarkis (layer presentation, application, domain, infrastructure).
   - Menghitung rute garis ortogonal antar kotak komponen dengan jarak (gap) konstan.

## 3. Ekspor dan Integrasi CI

Perintah CLI `php artisan docs:generate --format=svg` memindai seluruh folder `docs/`, menyusun diagram SVG ke folder `docs/generated/svg/`, dan memastikan tidak ada tautan diagram yang patah (`docs:check`).

