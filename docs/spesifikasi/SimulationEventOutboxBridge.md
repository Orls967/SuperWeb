# Spesifikasi Teknis: Simulation Outbox Bridge & Event Schema Integration (Fase 67 & R8)

Dokumen ini mendokumentasikan spesifikasi teknis antarmuka **Simulation Outbox Bridge**, integrasi dengan tabel `core_outbox`, spesifikasi skema JSON formal untuk event siklus hidup simulasi, dan jaminan pengiriman idempoten (*idempotent event publishing*).

---

## 1. Latar Belakang & Peran Arsitektural

Sesi simulasi waktu virtual tidak beroperasi dalam isolasi tertutup; setiap perubahan keadaan penting—seperti pembentukan sesi, perubahan status mesin status, penulisan checkpoint periodik, penyelesaian sesi, atau deteksi divergensi—harus disiarkan ke ekosistem platform yang lebih luas.

Penggunaan pola **Transactional Outbox Pattern** memastikan bahwa penulisan event terjadi dalam unit kerja basis data yang sama dengan pembaruan model `SimRun` dan `SimCheckpoint`. Jika transaksi basis data berhasil, event dijamin tersimpan di tabel `core_outbox` untuk kemudian dikonsumsi oleh Event Spine (Fase R8).

---

## 2. Skema Event Siklus Hidup Simulasi

Platform mendefinisikan empat tipe event resmi dengan versi skema `v1`:

### 2.1. `core.simulation.run_state_changed.v1`
Diterbitkan setiap kali terjadi transisi status sah pada `SimulationRunStateMachine`.
```json
{
  "event_id": "93a6c2f0-1234-4567-89ab-cdef01234567",
  "event_type": "core.simulation.run_state_changed.v1",
  "run_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "run_name": "benchmark-365d",
  "from_status": "provisioned",
  "to_status": "running",
  "current_day": 0,
  "chain_hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "reason": "Mulai eksekusi simulasi",
  "occurred_at": "2026-11-10T15:00:00Z"
}
```

### 2.2. `core.simulation.checkpoint_written.v1`
Diterbitkan setiap kali checkpoint milestone harian berhasil dipersistensikan.
```json
{
  "event_id": "uuid-v4",
  "event_type": "core.simulation.checkpoint_written.v1",
  "checkpoint_id": 42,
  "run_id": "uuid-v4",
  "day_number": 7,
  "chain_hash": "64-char-hex",
  "handler_hashes": {
    "hospital_discharge": "hash-a",
    "hotel_night_audit": "hash-b",
    "logistics_demurrage": "hash-c"
  },
  "occurred_at": "2026-11-10T15:05:00Z"
}
```

### 2.3. `core.simulation.day_completed.v1`
Diterbitkan pada akhir setiap hari virtual setelah seluruh tick handler tuntas.
```json
{
  "event_id": "uuid-v4",
  "event_type": "core.simulation.day_completed.v1",
  "run_id": "uuid-v4",
  "day_number": 1,
  "chain_hash": "64-char-hex",
  "metrics": {
    "events_count": 150,
    "elapsed_ms": 12.45
  },
  "occurred_at": "2026-11-10T15:01:00Z"
}
```

### 2.4. `core.simulation.divergence_detected.v1`
Diterbitkan saat pembanding dua sesi (`sim:diff`) mendeteksi ketidakcocokan rantai hash.
```json
{
  "event_id": "uuid-v4",
  "event_type": "core.simulation.divergence_detected.v1",
  "run_a_id": "uuid-v4",
  "run_b_id": "uuid-v4",
  "divergence_day": 14,
  "reason": "Divergensi hash rantai pada hari ke-14",
  "occurred_at": "2026-11-10T15:10:00Z"
}
```

---

## 3. Validasi Skema & Jaminan Idempotensi

Sebelum penulisan ke tabel `core_outbox`, kelas `SimulationEventCatalogService::validatePayload()` memverifikasi kelengkapan seluruh atribut wajib (*mandatory fields*). Jika atribut wajib hilang atau kosong, penulisan ditolak dengan melempar `InvalidArgumentException`.

Jaminan idempotensi dicapai melalui penyertaan `event_id` UUID unik pada setiap payload. Consumer event spine (Fase R8) menggunakan `event_id` ini untuk deduplikasi sebelum memproses mutasi domain.

