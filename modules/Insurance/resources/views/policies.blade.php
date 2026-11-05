<!DOCTYPE html>
<html>
<head><title>Daftar Polis Asuransi</title></head>
<body>
    <h1>Daftar Polis Asuransi</h1>
    <table>
        @foreach($policies as $policy)
            <tr>
                <td>{{ $policy->policy_number }}</td>
                <td>{{ $policy->gross_premium_idr }}</td>
                <td>{{ $policy->status }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>

