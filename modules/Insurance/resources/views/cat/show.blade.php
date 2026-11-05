<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail CAT Run {{ $run->run_code }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 1rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; border: none; cursor: pointer; }
        .btn-outline { background: #fff; color: #475569; border: 1px solid #cbd5e1; }
        table { width: 100%; border-collapse: collapse; text-align: left; margin-top: 0.5rem; }
        th, td { padding: 0.5rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
        th { background: #f8fafc; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ route('insurance.cat.index') }}" class="btn btn-outline" style="margin-bottom: 1rem;">&larr; Kembali</a>

        <div class="card">
            <h1>CAT Run {{ $run->run_code }}</h1>
            <div class="grid">
                <div><strong>Portofolio:</strong> {{ $run->portfolio_code }}</div>
                <div><strong>Seed Deterministik:</strong> {{ $run->seed }}</div>
                <div><strong>Tahun Simulasi:</strong> {{ $run->years }} tahun</div>
                <div><strong>Total Eksposur:</strong> Rp {{ number_format($run->total_exposure_idr, 0, ',', '.') }}</div>
                <div><strong>AAL:</strong> Rp {{ number_format($run->aal_idr, 0, ',', '.') }}</div>
                <div><strong>AEP RP100:</strong> Rp {{ number_format($run->aep_rp100_idr, 0, ',', '.') }}</div>
                <div><strong>AEP RP250:</strong> Rp {{ number_format($run->aep_rp250_idr, 0, ',', '.') }}</div>
                <div><strong>OEP RP100:</strong> Rp {{ number_format($run->oep_rp100_idr, 0, ',', '.') }}</div>
                <div><strong>TVaR RP100:</strong> Rp {{ number_format($run->tvar_rp100_idr, 0, ',', '.') }}</div>
                <div><strong>Hash Katalog:</strong> <code>{{ substr($run->catalog_hash, 0, 10) }}...</code></div>
            </div>
        </div>

        <div class="card">
            <h3>Eksposur per Region & Peril</h3>
            <table>
                <thead>
                    <tr>
                        <th>Region</th>
                        <th>Peril</th>
                        <th>Nilai Eksposur (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($run->exposures as $exp)
                        <tr>
                            <td>{{ $exp->region_code }}</td>
                            <td>{{ $exp->peril }}</td>
                            <td>Rp {{ number_format($exp->exposure_idr, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($plans->isNotEmpty())
            <div class="card">
                <h3>Rencana Struktur Proteksi Reasuransi</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Struktur</th>
                            <th>Attachment</th>
                            <th>Limit</th>
                            <th>Premi Layer</th>
                            <th>Ceded AAL</th>
                            <th>Net PML 100 Th</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($plans as $p)
                            <tr>
                                <td><strong>{{ $p->structure_code }}</strong></td>
                                <td>Rp {{ number_format($p->attachment_idr, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($p->limit_idr, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($p->layer_premium_idr, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($p->ceded_aal_idr, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($p->net_pml_rp100_idr, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</body>
</html>

