# Spesifikasi Integrasi: Kotak Masuk Tugas Saya & ApprovalEngine

**Fase:** 194 — Platform: Task Inbox & Approval Engine Bridge  
**Modul Pemilik:** `Core`  
**Komponen Penghubung:** `Modules\Core\Application\Actions\TaskInbox\ApprovalEngineTaskBridge`

---

## 1. Latar Belakang & Kebutuhan Integrasi

Sistem enterprise SuperWeb telah memiliki fondasi `ApprovalEngine` pada modul `Core` yang menangani rantai persetujuan berjenjang (*multi-tier approval workflow*), batas kewenangan finansial (*financial delegation of authority*), dan eskalasi otomatis.

Namun, sebelum Fase 194, setiap entitas yang membutuhkan persetujuan mengharuskan pemohon menghubungi approver secara manual atau approver harus membuka halaman detail transaksi masing-masing.

`ApprovalEngineTaskBridge` bertindak sebagai adaptor dua arah antara `ApprovalEngine` dan Kotak Masuk "Tugas Saya" (`TaskInboxService`).

---

## 2. Alur Kerja Sinkronisasi Dua Arah

```mermaid
flowchart TD
    DomainEvent["Event Domain: PurchaseOrderCreated / ComponentReleaseRequested"] --> Engine["ApprovalEngine"]
    Engine --> Bridge["ApprovalEngineTaskBridge"]
    
    subgraph "Pembuatan Tugas di Kotak Masuk"
        Bridge --> Inbox["TaskInboxService::createTask()"]
        Inbox --> DB_Task[("core_tasks & core_task_histories")]
    end
    
    subgraph "Eksekusi Keputusan dari /tugas-saya"
        Approver["Approver Menekan 'Setujui' / 'Tolak'"] --> Controller["TaskInboxController"]
        Controller --> InboxAction["TaskInboxService::completeTask()"]
        InboxAction --> BridgeCallback["ApprovalEngineTaskBridge::onTaskCompleted()"]
        BridgeCallback --> EngineApprove["ApprovalEngine::recordDecision()"]
        EngineApprove --> DomainCallback["Eksekusi Aksi Akhir Domain"]
    end
```

---

## 3. Penanganan Transisi & Idempotensi Bersama

Untuk mencegah keputusan ganda atau inkonsistensi status antara tabel `approval_requests` dan tabel `core_tasks`:
1. **Transaksi Atomik Database:** Mutasi status tugas di `core_tasks` dan pencatatan keputusan di `approval_requests` dibungkus dalam blok `DB::transaction`.
2. **Kunci Idempotensi Sinkron:** Kunci idempotensi ditransmisikan dari antarmuka web dan digunakan bersama oleh kedua layanan untuk mendeteksi penekanan tombol ganda (*double-click prevention*).
3. **Penyelarasan Riwayat:** Setiap transisi di `core_tasks` mencatat ID keputusan approval terkait di kolom metadata JSON.

