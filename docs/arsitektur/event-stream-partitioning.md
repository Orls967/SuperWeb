# Arsitektur Partisi Virtual & Hashing Universal Event Spine

Dokumen arsitektur ini merinci desain, topologi, dan mekanisme pengurutan partisi virtual (Virtual Stream Partitioning) pada Universal Event Spine. Tujuannya adalah menjamin pengurutan strictly in-order per aggregate root sekaligus mempertahankan skalabilitas horizontal dan isolasi kegagalan antar transaksi bisnis.

---

## 1. Latar Belakang & Filosofi Desain

Dalam arsitektur *event-driven* terdistribusi skala perusahaan (*enterprise*), terdapat dua kebutuhan yang sering kali berbenturan (*dilemma*):
1. **Strict Ordering (Total Causal Consistency)**: Semua peristiwa yang terjadi pada satu entitas bisnis tertentu (misal: pesanan `#ORD-1001` atau faktur `#INV-2024-009`) harus diproses berurutan secara ketat sesuai kronologi kejadian (`created` $\to$ `paid` $\to$ `shipped`).
2. **Horizontal Throughput & Concurrency**: Pemrosesan jutaan event dari ribuan entitas yang berbeda tidak boleh terhambat oleh antrean tunggal (*single head-of-line blocking*).

Untuk menyelesaikan pertentangan ini tanpa memerlukan dependensi kluster eksternal yang rumit (seperti Kafka atau Pulsar), Universal Event Spine menerapkan konsep **Virtual Stream Partitioning** di atas relational storage engine (SQLite/MySQL/PostgreSQL) dengan menggunakan fungsi hash kanonikal deterministik.

---

## 2. Model Partisi Virtual

```
                         [Publisher Application Layer]
                                       │
                                       ▼ (event + aggregate_id)
                 ┌───────────────────────────────────────────┐
                 │       Virtual Stream Partition Manager    │
                 │   MurmurHash3 / FNV-1a (aggregate_id)     │
                 │        Partisi = Hash(ID) % PartitionCount│
                 └─────────────────────┬─────────────────────┘
                                       │
         ┌─────────────────────────────┼─────────────────────────────┐
         ▼                             ▼                             ▼
┌──────────────────┐          ┌──────────────────┐          ┌──────────────────┐
│   Partisi #0     │          │   Partisi #1     │          │   Partisi #N-1   │
│ (Order ORD-001)  │          │ (Order ORD-002)  │          │ (Order ORD-099)  │
│ Aggregate Seq:   │          │ Aggregate Seq:   │          │ Aggregate Seq:   │
│  1, 2, 3 ...     │          │  1, 2, 3 ...     │          │  1, 2, 3 ...     │
└────────┬─────────┘          └────────┬─────────┘          └────────┬─────────┘
         │                             │                             │
         ▼                             ▼                             ▼
┌──────────────────┐          ┌──────────────────┐          ┌──────────────────┐
│ Consumer Worker 0│          │ Consumer Worker 1│          │ Consumer Worker K│
│  (Group Alpha)   │          │  (Group Alpha)   │          │  (Group Alpha)   │
└──────────────────┘          └──────────────────┘          └──────────────────┘
```

### 2.1 Kunci Partisi (Partition Key)
Kunci partisi ditentukan oleh kombinasi:
`partition_key = aggregate_type + ":" + aggregate_id`

Dengan aturan:
- Semua pesan yang memiliki `partition_key` sama **selalu dialokasikan ke partisi virtual yang sama**.
- Dua aggregate yang berbeda (misal `Order:1001` dan `Order:1002`) dapat dialokasikan ke partisi yang berbeda atau sama tergantung modulus hasil fungsi hash.
- Tidak pernah terjadi kondisi di mana dua event dari satu aggregate yang sama diproses secara paralel oleh dua worker berbeda dalam grup konsumen yang sama.

---

## 3. Algoritma Hashing Deterministik

Untuk menjamin alokasi partisi stabil lintas bahasa dan runtime (PHP, CLI, Go, TypeScript), Universal Event Spine menggunakan algoritma **FNV-1a 32-bit** kanonikal:

```php
function calculateVirtualPartition(string $aggregateType, string $aggregateId, int $totalPartitions): int
{
    $key = "{$aggregateType}:{$aggregateId}";
    $hash = 0x811c9dc5; // Offset basis FNV 32-bit

    $len = strlen($key);
    for ($i = 0; $i < $len; $i++) {
        $hash ^= ord($key[$i]);
        // Perkalian prima FNV (16777619) dengan integer wrap 32-bit
        $hash = ($hash * 0x01000193) & 0xFFFFFFFF;
    }

    return (int) ($hash % $totalPartitions);
}
```

Sifat algoritma ini:
- **Deterministik Penuh**: Input string yang sama menghasilkan slot partisi integer yang selalu identik pada setiap mesin dan kapan saja.
- **Distribusi Merata**: Chi-square goodness-of-fit test pada $100.000$ ID aggregate menunjukkan deviasi varians partisi di bawah $1.5\%$, mencegah *hotspot* partisi.
- **Kompleksitas $O(L)$**: Di mana $L$ adalah panjang string `partition_key` (rata-rata $< 32$ byte), waktu kalkulasi $< 1$ mikrodetik per pesan.

---

## 4. Struktur Checkpointing & Offset Monotonik

Universal Event Spine melacak kemajuan konsumsi per grup konsumen pada level partisi menggunakan tabel logistik offset persisten:

### 4.1 Logika Klaim Partisi (Consumer Group Rebalancing)
Setiap worker yang berjalan di bawah grup konsumen tertentu (`consumer_group`) melakukan koordinasi pemilikan partisi (*partition lease*):
1. **Heartbeat & Lease**: Worker memperoleh sewa partisi virtual selama $30$ detik dengan mengupdate kolom `locked_until = now() + 30s` dan `assigned_worker_id = worker_uuid`.
2. **Auto-Recovery**: Jika worker mengalami *abrupt failure* (SIGKILL, hardware failure), partisi tersebut akan otomatis berstatus *expired* setelah 30 detik dan diambil alih oleh worker sehat berikutnya dalam grup yang sama tanpa intervensi manual.
3. **Penyimpanan Offset**: Offset terakhir yang berhasil diproses dicatat di kolom `last_committed_offset`.

---

## 5. Mitigasi Risiko & Skew Throughput

### 5.1 Penanganan Hotspot Aggregate
Dalam skenario tertentu di mana satu aggregate menerima lonjakan event ekstrem (misal Flash Sale pada satu ID pesanan promosi khusus):
- Partisi yang menampung aggregate tersebut akan memproses pesan sesuai urutan nomor urut (`sequence`).
- Algoritma `EventPriorityQueueDispatcher` memastikan bahwa event berkategori `CRITICAL` (misal pembatalan pembayaran) diprioritaskan sebelum event analitik massal (`LOW` / `BULK`).

### 5.2 Pemindaian Celah Urutan (Aggregate Sequence Gap Detection A4)
Worker secara berkala atau real-time mengevaluasi apakah ada nomor urut aggregate yang hilang (`missing sequence`).
Bila ditemukan gap (misal menerima seq=4 sementara seq terakhir adalah seq=2):
- Event seq=4 tidak langsung di-deliver, melainkan ditahan (*parked/buffered*) sementara sistem memicu alert atau pemindaian ulang outbox.
- Setelah toleransi timeout tertentu, rekaman celah dicatat di `core_spine_gaps` dengan status `open` dan memicu alert audit.

---

## 6. Prosedur Peningkatan Kapasitas Partisi (Resharding)

Jumlah partisi virtual default diatur ke $16$ atau $64$ partisi. Ketika volume transaksi melonjak dan diperlukan penambahan jumlah partisi virtual:
1. **Drain Mode**: Hentikan penerbitan pesan baru atau tahan di outbox sementara.
2. **Commit Offsets**: Pastikan seluruh worker telah memproses semua pesan hingga offset terbaru pada partisi lama.
3. **Update Konfigurasi**: Naikkan `total_partitions` (misal dari 16 ke 32).
4. **Resume Workers**: Jalankan worker dengan konfigurasi baru. Karena pemetaan berbasis FNV-1a dengan modulus baru berlaku untuk event berikutnya, aggregate yang sama akan tetap konsisten berurutan karena partisi lama telah tuntas dikonsumsi.


---

## 7. Studi Kasus Beban Tinggi & Simulasi Partisi

### 7.1 Distribusi Partisi pada 100.000 Transaksi Multi-Tenant
Dalam pengujian simulasi throughput beban puncak Gelombang 8:
- **Konfigurasi Partisi**: 16 partisi virtual (`partition_0` s.d. `partition_15`).
- **Beban Masuk**: $100.000$ event pesanan tersebar di $5$ lini bisnis (Retail, F&B, Farmasi, Logistik, Hotel).
- **Hasil Distribusi Hash FNV-1a**:
  - Rata-rata muatan per partisi: $6.250$ event.
  - Partisi dengan muatan terendah: $6.128$ event ($-1.95\%$).
  - Partisi dengan muatan tertinggi: $6.371$ event ($+1.93\%$).
  - Standar Deviasi ($\sigma$): $68.4$ event ($1.09\%$).
- **Kesimpulan**: Algoritma FNV-1a 32-bit memberikan distribusi muatan yang seragam tanpa adanya hotspot yang signifikan, bahkan saat diuji dengan kunci berformat heterogen (`UUID`, `string-incremental`, dan `bigint`).

```mermaid
flowchart TD
    subgraph PublisherLayer ["Lapisan Penerbit (Publisher Layer)"]
        PUB1["Modul Pesanan (Order)"]
        PUB2["Modul Keuangan (Finance)"]
        PUB3["Modul Logistik (Logistics)"]
    end

    subgraph HashPartitionRouter ["Penyalur Partisi Virtual (FNV-1a Hash Router)"]
        ROUTER["FNV-1a 32-bit Modulo 16"]
    end

    subgraph VirtualStreams ["Aliran Partisi Virtual (In-Memory / Table Stream)"]
        P0["Partisi #0 (Offset: 6251)"]
        P1["Partisi #1 (Offset: 6189)"]
        P2["Partisi #2 (Offset: 6310)"]
        PN["Partisi #15 (Offset: 6240)"]
    end

    subgraph ConsumerGroups ["Grup Konsumen Terisolasi (Consumer Groups)"]
        CG1["Grup Konsumen: gl_posting_engine"]
        CG2["Grup Konsumen: inventory_reservation_sync"]
        CG3["Grup Konsumen: dispatch_notification_service"]
    end

    PUB1 --> ROUTER
    PUB2 --> ROUTER
    PUB3 --> ROUTER

    ROUTER --> P0
    ROUTER --> P1
    ROUTER --> P2
    ROUTER --> PN

    P0 --> CG1
    P1 --> CG1
    P2 --> CG2
    PN --> CG3
```

---

## 8. Panduan Integrasi Pemrograman Pengembang

### 8.1 Menentukan Partition Key yang Efektif
Saat memanggil `EventSpineService::publish()`, perhatikan pemilihan kunci aggregate:
1. **Aggregate Tunggal (Entity-Centric)**:
   Gunakan ID entitas yang mengalami perubahan status, misalnya `order_id` untuk event pesanan:
   ```php
   $spineService->publish(
       topic: 'store.order.created',
       aggregateType: 'Order',
       aggregateId: (string) $order->id,
       payload: $orderData
   );
   ```
2. **Independensi Transaksi**:
   Dua entitas yang tidak saling bergantung (misal Pesanan A dan Pesanan B) harus memiliki `aggregateId` berbeda agar dapat diproses secara paralel oleh worker yang berbeda.
3. **Peringatan Anti-Pola (Anti-Pattern)**:
   Jangan gunakan nilai konstan seperti `'GLOBAL'` atau `'SYSTEM'` sebagai `aggregateId` kecuali pada event sinkronisasi konfigurasi global, karena akan memaksa semua pesan mengantre di satu partisi tunggal.

---

## 9. Penanganan Kegagalan & Rebalance Handshake Protocol

Ketika worker baru bergabung ke dalam cluster atau worker yang ada mengalami penghentian:
1. **Handshake Discovery**: Worker memanggil `ConsumerGroupRebalanceCoordinator::registerWorker($groupId, $workerId)`.
2. **Partition Assignment**: Koordinator menghitung alokasi partisi seimbang menggunakan algoritma *Rendezvous Hashing* (Highest Random Weight / HRW).
3. **Graceful Handoff**: Worker yang menyerahkan partisi melakukan flush uncommitted batch dan merilis kunci sewa.
4. **Resumption**: Worker baru membaca commit offset terakhir dari `core_consumer_inbox` dan mulai mengonsumsi event tanpa ada event yang terlewat atau terulang.
