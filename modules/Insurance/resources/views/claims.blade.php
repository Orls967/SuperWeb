<!DOCTYPE html>
<html>
<head><title>Daftar Klaim Asuransi</title></head>
<body>
    <h1>Daftar Klaim Asuransi</h1>
    <table>
        @foreach($claims as $claim)
            <tr>
                <td>{{ $claim->claim_number }}</td>
                <td>{{ $claim->claimed_amount_idr }}</td>
                <td>{{ $claim->status }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>

