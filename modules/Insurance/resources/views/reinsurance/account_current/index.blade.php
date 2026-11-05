<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Current Reasuransi</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 1rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 1024px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; border: none; cursor: pointer; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
        th { background: #f1f5f9; font-weight: 600; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.25rem; font-size: 0.875rem; font-weight: 500; }
        input, select { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Account Current Reasuransi (Statement Periodik)</h1>
        @if(session('status'))
            <div style="background: #dcfce7; color: #166534; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem;">
                {{ session('status') }}
            </div>
        @endif

        <div class="card">
            <h2>Generate Statement Account Current</h2>
            <form action="{{ route('insurance.reinsurance.account-current.generate') }}" method="POST">
                @csrf
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 2; min-width: 200px;">
                        <label>Reinsurer Party</label>
                        <select name="reinsurer_party_id" required>
                            @foreach($reinsurers as $r)
                                <option value="{{ $r->id }}">{{ $r->legal_name ?? $r->name ?? 'Party #'.$r->id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Periode (YYYY-MM)</label>
                        <input type="text" name="period_ym" required value="{{ date('Y-m') }}">
                    </div>
                </div>
                <button type="submit" class="btn">Generate Statement</button>
            </form>
        </div>

        <div class="card">
            <h2>Daftar Statement Account Current</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nomor</th>
                            <th>Reinsurer</th>
                            <th>Periode</th>
                            <th>Premi Ceded</th>
                            <th>Komisi</th>
                            <th>Klaim Recoverable</th>
                            <th>Net Due</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accountCurrents as $ac)
                            <tr>
                                <td><strong>{{ $ac->number }}</strong></td>
                                <td>{{ $ac->reinsurer?->legal_name ?? 'Party #'.$ac->reinsurer_party_id }}</td>
                                <td>{{ $ac->period_ym }}</td>
                                <td>Rp {{ number_format($ac->premium_ceded_idr, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($ac->commission_idr, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($ac->claims_recoverable_idr, 0, ',', '.') }}</td>
                                <td><strong>Rp {{ number_format($ac->net_due_idr, 0, ',', '.') }}</strong></td>
                                <td><span style="font-weight: 600;">{{ strtoupper($ac->status->value) }}</span></td>
                                <td><a href="{{ route('insurance.reinsurance.account-current.show', $ac->id) }}" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9">Belum ada statement account current.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1rem;">{{ $accountCurrents->links() }}</div>
        </div>
    </div>
</body>
</html>

