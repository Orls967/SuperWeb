<!DOCTYPE html>
<html>
<head><title>Detail Polis Asuransi</title></head>
<body>
    <h1>Polis {{ $policy->policy_number }}</h1>
    <p>Sum Insured: {{ $policy->sum_insured_idr }}</p>
    <p>Gross Premium: {{ $policy->gross_premium_idr }}</p>
    <p>UPR: {{ $uprAmount }}</p>
</body>
</html>

