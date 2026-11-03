# Desain Teknis — Lini 27: Pelabuhan & Terminal (modul Port prt_, Fase 179, P1.4)

## 1. Arsitektur Modul Port & Keputusan Kunci
Modul `Port` (`prt_`) mengelola operasional terminal peti kemas laut dan dermaga pelabuhan:
- **D27-1 (Master Data & Kontrak):**
  - Master kapal (`lgx_vessels`) dan kontainer (`lgx_containers`) tetap berada di modul `Logistics`.
  - Modul Port mengakses data kapal melalui Kontrak K4 `Modules\Logistics\Contracts\VesselRegistry` (`findByImo`, `containerById`). Pembuatan tabel kapal ganda dilarang keras.
  - Perhitungan Demurrage & Detention kontainer wajib mendelegasikan ke kalkulator resmi Logistics via Kontrak K5 `Modules\Logistics\Contracts\DemurrageDetentionQuote`.
- **D27-2 (Invarian Yard Kontainer):**
  - Pada setiap terminal pelabuhan:
    $$\text{Kontainer Masuk} = \text{Kontainer Keluar} + \text{Kontainer di Lapangan (Yard)}$$
  - Invarian ini dievaluasi secara append-only dari log transaksi pergerakan gate in/out dan alokasi slot lapangan.
- **D27-3 (Algoritma Berth Allocation):**
  - `Modules\Shared\Domain\Algorithms\Scheduling\BerthAllocation`:
    - Minimalkan: $\sum (\text{bobot prioritas}_i \times \text{waktu tunggu}_i)$ di mana $\text{waktu tunggu}_i = \text{waktu mulai}_i - \text{waktu tiba}_i$.
    - Eksak (Branch and Bound dengan batas bawah) untuk $\le 8$ kapal per jendela perencanaan; Heuristik untuk $> 8$ kapal.
    - Tie-break deterministik: (1) nilai objektif minimum; (2) vektor waktu mulai per ID kapal, leksikografis terkecil; (3) vektor indeks tambatan, leksikografis terkecil.
    - Vektor benchmark prompt: 2 tambatan; V1 (tiba 0, layan 10, bobot 1), V2 (1, 4, 2), V3 (2, 6, 1), V4 (3, 3, 3), V5 (5, 5, 1) $\rightarrow$ optimum 14; Tambatan 1: V4 (03–06), V1 (06–16); Tambatan 2: V2 (01–05), V5 (05–10), V3 (10–16).
- **D27-4 (Posting Akuntansi Double-Entry):**
  - Terminal Handling Charge (THC):
    - **Debit:** `ar:prt:line:{party}:IDR`
    - **Kredit:** `rev:prt:thc:IDR`
  - Jasa Dermaga (Dockage):
    - **Debit:** `ar:prt:line:{party}:IDR`
    - **Kredit:** `rev:prt:dockage:IDR`
  - Penumpukan Yard (Storage):
    - **Debit:** `ar:prt:line:{party}:IDR`
    - **Kredit:** `rev:prt:storage:IDR`
  - D&D (nilai dari K5):
    - **Debit:** `ar:prt:line:{party}:IDR`
    - **Kredit:** `prt:dnd_revenue:IDR`
- **D27-5 (Audit Dua Sumber):**
  - Perintah `php artisan prt:audit` memvalidasi invarian yard kontainer dan rekonsiliasi total tagihan terminal terhadap jurnal buku besar.

## 2. Struktur Database (6 Tabel `prt_*`)
1. `prt_berths`: tambatan dermaga (kode tambatan, panjang meter, draft meter).
2. `prt_vessel_calls`: kunjungan kapal (vessel_id K4, call_sign, IMO, ETA, ATA, ATD, status tambat).
3. `prt_yard_blocks`: blok lapangan penumpukan kontainer (nama blok, kapasitas TEU, tipe muatan).
4. `prt_yard_slots`: penempatan kontainer per slot lapangan (slot_coord, container_id K4, status dwell).
5. `prt_gate_passes`: transaksi gate in/out truk pembawa kontainer (gate_type: IN/OUT, container_id K4, truck_plate, timestamp).
6. `prt_terminal_bills`: tagihan terminal terbitan (line_party_id, bill_number, thc_amount, dockage_amount, storage_amount, dnd_amount, total_amount_idr, status).
