<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risk-Based Capital & Solvabilitas</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 1rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 1024px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; border: none; cursor: pointer; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .badge-healthy { background: #dcfce7; color: #166534; }
        .badge-early_warning { background: #fef3c7; color: #92400e; }
        .badge-regulator { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
        th { background: #f1f5f9; font-weight: 600; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
        .metric { background: #f8fafc; padding: 1rem; border-radius: 6px; border-left: 4px solid #2563eb; }
        .metric-title { font-size: 0.8rem; color: #64748b; text-transform: uppercase; }
        .metric-value { font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Solvabilitas & Risk-Based Capital (D157-4)</h1>
        @if(session('status'))
            <div style="background: #dcfce7; color: #166534; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem;">
                {{ session('status') }}
            </div>
        @endif

        @if($latest)
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap;">
                    <h2 style="margin: 0;">Valuasi Terkini: {{ $latest->valuation_date->format('d M Y') }}</h2>
                    <span class="badge badge-{{ $latest->alert_level->value }}">{{ $latest->alert_level->label() }}</span>
                </div>
                <div class="grid">
                    <div class="metric">
                        <div class="metric-title">Modal Tersedia</div>
                        <div class="metric-value">Rp {{ number_format($latest->available_capital_idr, 0, ',', '.') }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-title">Kebutuhan RBC (BigInt)</div>
                        <div class="metric-value">Rp {{ number_format($latest->rbc_idr, 0, ',', '.') }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-title">Rasio Solvabilitas</div>
                        <div class="metric-value">{{ round($latest->ratio_bps / 100, 2) }}% ({{ $latest->ratio_bps }} bps)</div>
                    </div>
                </div>

                @if($latest->actions->isNotEmpty())
                    <div style="margin-top: 1.5rem; background: #fffbeb; border: 1px solid #fde68a; padding: 1rem; border-radius: 6px;">
                        <h3 style="margin-top: 0; color: #92400e;">Rekomendasi Aksi Perbaikan Rasio Solvabilitas (What-If)</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Aksi</th>
                                    <th>Parameter</th>
                                    <th>Simulasi Rasio Setelah Aksi</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($latest->actions as $action)
                                    <tr>
                                        <td><strong>{{ $action->action_type->label() }}</strong></td>
                                        <td>{{ json_encode($action->params) }}</td>
                                        <td><strong>{{ round($action->simulated_ratio_bps / 100, 2) }}% ({{ $action->simulated_ratio_bps }} bps)</strong></td>
                                        <td>{{ strtoupper($action->status->value) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        <div class="card">
            <h2>Hitung Snapshot Baru</h2>
            <form action="{{ route('insurance.capital.snapshot') }}" method="POST" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                @csrf
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 0.25rem; font-size: 0.875rem;">Tanggal Valuasi</label>
                    <input type="date" name="valuation_date" value="{{ date('Y-m-d') }}" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                </div>
                <button type="submit" class="btn">Hitung Solvabilitas</button>
            </form>
        </div>

        <div class="card">
            <h2>Riwayat Snapshot Valuasi</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Modal Tersedia</th>
                        <th>Nilai RBC</th>
                        <th>Rasio Solvabilitas</th>
                        <th>Alert Level</th>
                        <th>Hash Input</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($snapshots as $s)
                        <tr>
                            <td><strong>{{ $s->valuation_date->format('Y-m-d') }}</strong></td>
                            <td>Rp {{ number_format($s->available_capital_idr, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($s->rbc_idr, 0, ',', '.') }}</td>
                            <td><strong>{{ round($s->ratio_bps / 100, 2) }}%</strong></td>
                            <td><span class="badge badge-{{ $s->alert_level->value }}">{{ $s->alert_level->label() }}</span></td>
                            <td><code style="font-size: 0.75rem;">{{ substr($s->input_hash, 0, 10) }}...</code></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Belum ada snapshot valuasi.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="margin-top: 1rem;">{{ $snapshots->links() }}</div>
        </div>
    </div>
</body>
</html>

