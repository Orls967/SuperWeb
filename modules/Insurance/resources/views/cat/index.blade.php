<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catastrophe Modelling</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 1rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 1024px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; border: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
        th { background: #f1f5f9; font-weight: 600; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.25rem; font-size: 0.875rem; font-weight: 500; }
        input { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .metric { background: #f8fafc; padding: 1rem; border-radius: 6px; border-left: 4px solid #059669; }
        .metric-title { font-size: 0.8rem; color: #64748b; text-transform: uppercase; }
        .metric-value { font-size: 1.25rem; font-weight: 700; margin-top: 0.25rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Pemodelan Bencana Katastrofe (CAT Modelling, D157-5)</h1>
        @if(session('status'))
            <div style="background: #dcfce7; color: #166534; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem;">
                {{ session('status') }}
            </div>
        @endif

        @if($latest)
            <div class="card">
                <h2>Hasil Run Terakhir: {{ $latest->run_code }}</h2>
                <div class="grid">
                    <div class="metric">
                        <div class="metric-title">Total Eksposur Portofolio</div>
                        <div class="metric-value">Rp {{ number_format($latest->total_exposure_idr, 0, ',', '.') }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-title">AAL (Average Annual Loss)</div>
                        <div class="metric-value">Rp {{ number_format($latest->aal_idr, 0, ',', '.') }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-title">PML / AEP 100 Tahun</div>
                        <div class="metric-value">Rp {{ number_format($latest->aep_rp100_idr, 0, ',', '.') }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-title">PML / AEP 250 Tahun</div>
                        <div class="metric-value">Rp {{ number_format($latest->aep_rp250_idr, 0, ',', '.') }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-title">OEP 100 Tahun</div>
                        <div class="metric-value">Rp {{ number_format($latest->oep_rp100_idr, 0, ',', '.') }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-title">TVaR 100 Tahun</div>
                        <div class="metric-value">Rp {{ number_format($latest->tvar_rp100_idr, 0, ',', '.') }}</div>
                    </div>
                </div>

                @if($plans->isNotEmpty())
                    <h3 style="margin-top: 1.5rem;">Perbandingan Rencana Proteksi Reasuransi CAT:</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Struktur</th>
                                <th>Attachment</th>
                                <th>Limit</th>
                                <th>Premi Layer</th>
                                <th>Ceded AAL</th>
                                <th>Net PML RP100</th>
                                <th>Net PML RP250</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($plans as $plan)
                                <tr>
                                    <td><strong>{{ $plan->structure_code }}</strong></td>
                                    <td>Rp {{ number_format($plan->attachment_idr, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($plan->limit_idr, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($plan->layer_premium_idr, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($plan->ceded_aal_idr, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($plan->net_pml_rp100_idr, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($plan->net_pml_rp250_idr, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        <div class="card">
            <h2>Jalankan Simulasi CAT Baru</h2>
            <form action="{{ route('insurance.cat.run') }}" method="POST">
                @csrf
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Portofolio</label>
                        <input type="text" name="portfolio_code" value="GENERAL" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Seed Deterministik</label>
                        <input type="number" name="seed" value="20261106" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Tahun Simulasi (YLT)</label>
                        <input type="number" name="years" value="250" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Event per Peril</label>
                        <input type="number" name="events_per_peril" value="10" required>
                    </div>
                </div>
                <button type="submit" class="btn">Jalankan Simulasi Deterministik</button>
            </form>
        </div>

        <div class="card">
            <h2>Riwayat Simulasi CAT</h2>
            <table>
                <thead>
                    <tr>
                        <th>Run Code</th>
                        <th>Seed</th>
                        <th>Tahun</th>
                        <th>AAL</th>
                        <th>AEP RP100</th>
                        <th>AEP RP250</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($runs as $r)
                        <tr>
                            <td><strong>{{ $r->run_code }}</strong></td>
                            <td>{{ $r->seed }}</td>
                            <td>{{ $r->years }} th</td>
                            <td>Rp {{ number_format($r->aal_idr, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($r->aep_rp100_idr, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($r->aep_rp250_idr, 0, ',', '.') }}</td>
                            <td><span style="font-weight: 600;">{{ strtoupper($r->status->value) }}</span></td>
                            <td><a href="{{ route('insurance.cat.show', $r->id) }}" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">Belum ada run simulasi CAT.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="margin-top: 1rem;">{{ $runs->links() }}</div>
        </div>
    </div>
</body>
</html>

