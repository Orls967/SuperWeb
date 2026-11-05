<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bordereau {{ $bordereau->number }}</title>
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
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
            <a href="{{ route('insurance.reinsurance.bordereaux.index') }}" class="btn btn-outline">&larr; Kembali</a>
            <a href="{{ route('insurance.reinsurance.bordereaux.export-csv', $bordereau->id) }}" class="btn" style="background: #059669;">Ekspor CSV</a>
        </div>

        <div class="card">
            <h1>Bordereau {{ $bordereau->number }}</h1>
            <div class="grid">
                <div><strong>Treaty:</strong> {{ $bordereau->treaty?->treaty_code }} - {{ $bordereau->treaty?->name }}</div>
                <div><strong>Reinsurer:</strong> {{ $bordereau->reinsurer?->legal_name ?? 'Party #'.$bordereau->reinsurer_party_id }}</div>
                <div><strong>Jenis:</strong> {{ ucfirst($bordereau->kind) }}</div>
                <div><strong>Periode:</strong> {{ $bordereau->period_ym }}</div>
                <div><strong>Status:</strong> {{ strtoupper($bordereau->status->value) }}</div>
                <div><strong>Total Ceded:</strong> Rp {{ number_format($bordereau->total_ceded_idr, 0, ',', '.') }}</div>
                <div><strong>Komisi:</strong> Rp {{ number_format($bordereau->total_commission_idr, 0, ',', '.') }}</div>
                <div><strong>Reinstatement:</strong> Rp {{ number_format($bordereau->total_reinstatement_idr, 0, ',', '.') }}</div>
            </div>

            @if($bordereau->status === \Modules\Insurance\Domain\Enums\BordereauStatus::Draft)
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                    <form action="{{ route('insurance.reinsurance.bordereaux.send', $bordereau->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn">Kirim Bordereau ke Reinsurer (Final & Immutable)</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="card">
            <h3>Rincian Polis / Klaim dalam Bordereau ({{ $bordereau->lines->count() }} baris)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Line ID</th>
                        <th>Cession Line ID</th>
                        <th>Jumlah Ceded</th>
                        <th>Komisi</th>
                        <th>Reinstatement</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bordereau->lines as $line)
                        <tr>
                            <td>{{ $line->id }}</td>
                            <td>{{ $line->cession_line_id }}</td>
                            <td>Rp {{ number_format($line->amount_idr, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($line->commission_idr, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($line->reinstatement_idr, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

