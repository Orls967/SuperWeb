<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Treaty Reasuransi</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 1rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 1024px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; border: none; cursor: pointer; }
        .btn:hover { background: #1d4ed8; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
        th { background: #f1f5f9; font-weight: 600; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-draft { background: #fef3c7; color: #92400e; }
        .badge-bound { background: #e0e7ff; color: #3730a3; }
        .badge-commuted { background: #f1f5f9; color: #475569; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.25rem; font-size: 0.875rem; font-weight: 500; }
        input, select { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        @media (max-width: 640px) { body { padding: 0.5rem; } .card { padding: 1rem; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>Register Program Treaty Reasuransi (Lini 18)</h1>
        @if(session('status'))
            <div style="background: #dcfce7; color: #166534; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem;">
                {{ session('status') }}
            </div>
        @endif

        <div class="card">
            <h2>Daftar Treaty Aktif & Draf</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Tipe</th>
                            <th>Lini</th>
                            <th>Status</th>
                            <th>Inception - Expiry</th>
                            <th>Panel</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($treaties as $treaty)
                            <tr>
                                <td><strong>{{ $treaty->treaty_code }}</strong></td>
                                <td>{{ $treaty->name }}</td>
                                <td>{{ $treaty->type->label() }}</td>
                                <td>{{ $treaty->line_category }}</td>
                                <td><span class="badge badge-{{ $treaty->status->value }}">{{ $treaty->status->label() }}</span></td>
                                <td>{{ $treaty->inception_date->format('Y-m-d') }} - {{ $treaty->expiry_date->format('Y-m-d') }}</td>
                                <td>{{ $treaty->panel->count() }} reinsurer</td>
                                <td><a href="{{ route('insurance.reinsurance.treaties.show', $treaty->id) }}" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8">Belum ada program treaty terdaftar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1rem;">{{ $treaties->links() }}</div>
        </div>

        <div class="card">
            <h2>Buat Treaty Baru</h2>
            <form action="{{ route('insurance.reinsurance.treaties.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Nama Treaty</label>
                    <input type="text" name="name" required placeholder="Contoh: Motor Quota Share 2026">
                </div>
                <div class="form-group">
                    <label>Tipe Treaty</label>
                    <select name="type" required>
                        <option value="quota_share">Quota Share</option>
                        <option value="surplus">Surplus</option>
                        <option value="xol_risk">XoL Per-Risk</option>
                        <option value="xol_cat">Cat XoL</option>
                        <option value="stop_loss">Stop Loss</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kategori Lini</label>
                    <input type="text" name="line_category" required value="VEHICLE">
                </div>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Inception Date</label>
                        <input type="date" name="inception_date" required value="{{ date('Y-01-01') }}">
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Expiry Date</label>
                        <input type="date" name="expiry_date" required value="{{ date('Y-12-31') }}">
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Quota Share (bps)</label>
                        <input type="number" name="quota_share_bps" value="4000">
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Komisi Reasuransi (bps)</label>
                        <input type="number" name="commission_bps" value="1500">
                    </div>
                </div>
                <div class="form-group">
                    <label>Panel Reinsurer Utama (Share 10.000 bps = 100%)</label>
                    <select name="panel[0][reinsurer_party_id]" required>
                        @foreach($reinsurers as $reinsurer)
                            <option value="{{ $reinsurer->id }}">{{ $reinsurer->legal_name ?? $reinsurer->name ?? 'Party #'.$reinsurer->id }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="panel[0][share_bps]" value="10000">
                </div>
                <button type="submit" class="btn">Buat Draf Treaty</button>
            </form>
        </div>
    </div>
</body>
</html>

