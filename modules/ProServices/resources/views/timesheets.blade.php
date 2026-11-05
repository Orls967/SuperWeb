<!DOCTYPE html>
<html>
<head><title>Timesheets</title></head>
<body>
    <h1>Timesheets</h1>
    <ul>
        @foreach($timesheets as $t)
            <li>{{ $t->timesheet_code }} - {{ $t->hours_worked }} hours</li>
        @endforeach
    </ul>
</body>
</html>
