# Definition of Ready (DoR) - P5.1 Lini 25 Jasa Profesional (ProServices `psv_`, Fase 175)

## 1. Modul & Prefiks
- **Modul**: `ProServices` (Namespace `Modules\ProServices`)
- **Prefiks Tabel**: `psv_`
- **Acuan**: KONSEP.md baris 18, 25, §A1, §A2, §A7, §A16; PROGRESS.md Fase 175.

## 2. Tabel & Skema Relasional
1. `psv_projects`
   - `id`: bigint unsigned auto_increment primary key
   - `project_code`: string unique
   - `client_party_id`: unsignedBigInteger
   - `contract_reference`: string
   - `billing_type`: string enum('time_and_materials', 'fixed_fee')
   - `total_contract_idr`: bigint unsigned default 0
   - `estimated_cost_idr`: bigint unsigned default 0
   - `status`: string ('draft', 'active', 'completed', 'closed')
   - `timestamps`
2. `psv_project_members`
   - `id`: bigint unsigned auto_increment primary key
   - `project_id`: foreign key `psv_projects.id`
   - `employee_id`: unsignedBigInteger (K12)
   - `role_name`: string
   - `hourly_rate_idr`: bigint unsigned
   - `active`: boolean default true
   - `timestamps`
   - unique: `[project_id, employee_id]`
3. `psv_timesheets`
   - `id`: bigint unsigned auto_increment primary key
   - `timesheet_code`: string unique
   - `project_id`: foreign key `psv_projects.id`
   - `project_member_id`: foreign key `psv_project_members.id`
   - `work_date`: date
   - `hours_worked`: int
   - `description`: text
   - `hourly_rate_idr`: bigint unsigned
   - `status`: string ('submitted', 'approved', 'rejected')
   - `approved_by_user_id`: unsignedBigInteger nullable
   - `approved_at`: timestamp nullable
   - `is_billed`: boolean default false
   - `is_reversal`: boolean default false
   - `reversed_timesheet_id`: unsignedBigInteger nullable
   - `timestamps`
4. `psv_invoices`
   - `id`: bigint unsigned auto_increment primary key
   - `invoice_code`: string unique
   - `project_id`: foreign key `psv_projects.id`
   - `client_party_id`: unsignedBigInteger
   - `subtotal_idr`: bigint unsigned
   - `idempotency_key`: string unique
   - `status`: string ('issued', 'paid', 'cancelled')
   - `timestamps`
5. `psv_revenue_recognitions`
   - `id`: bigint unsigned auto_increment primary key
   - `recognition_code`: string unique
   - `project_id`: foreign key `psv_projects.id`
   - `cumulative_cost_idr`: bigint unsigned
   - `total_estimated_cost_idr`: bigint unsigned
   - `cumulative_recognized_idr`: bigint unsigned
   - `period_recognized_idr`: bigint unsigned
   - `certificate_sha256`: string
   - `idempotency_key`: string unique
   - `timestamps`

## 3. Akun Ledger & Arah Posting (D25-4, U-3)
| Kejadian | Debit | Kredit |
|---|---|---|
| Pengakuan Pendapatan | `psv:contract_asset:{project}:IDR` | `psv:revenue:IDR` |
| Tagihan (s/d nilai aset kontrak) | `ar:psv:client:{party}:IDR` | `psv:contract_asset:{project}:IDR` |
| Tagihan (melebihi aset kontrak) | `ar:psv:client:{party}:IDR` | `psv:contract_liability:{project}:IDR` |
| Pembayaran Klien | `bank:wallet:clearing:IDR` (atau wallet) | `ar:psv:client:{party}:IDR` |

Akun terdaftar di `ProServicesAccounts.php` dan `database/seeders/LedgerAccountsSeeder.php`.

## 4. Role & Rute (U-5, U-10)
- `/proservices/projects` (project_manager, 200/302, non-authorized 403)
- `/proservices/timesheets` (consultant, miliknya)
- `/proservices/portal/invoices` (client, tagihannya)

## 5. Audit Dua Sumber (U-11)
- Command: `psv:audit`
- Memeriksa 4 invariant:
  1. Jam tertagih = jam disetujui
  2. Total tagihan = total mutasi pada ledger piutang
  3. Total pengakuan pendapatan <= nilai kontrak
  4. Saldo aset kontrak / liabilitas kontrak = diakui - ditagih
- Fixture: `app/Quality/Audit/Fixtures/ProServicesAuditCorruptionFixture.php`
- Clean seed exit 0; corruption fixture exit != 0.

## 6. Tingkat Simulasi & Skala (U-7, U-12)
- S1 parameter rate card, milestone;
- T1 Seeder: 50 proyek, 20.000 baris timesheet.
