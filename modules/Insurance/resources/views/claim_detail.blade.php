<!DOCTYPE html>
<html>
<head><title>Detail Klaim Asuransi</title></head>
<body>
    <h1>Klaim {{ $claim->claim_number }}</h1>
    <p>Claimed: {{ $claim->claimed_amount_idr }}</p>
    <p>Approved: {{ $claim->approved_amount_idr }}</p>
    <p>Net Payout: {{ $claim->net_payout_idr }}</p>
    <p>Status: {{ $claim->status }}</p>
</body>
</html>

