<!DOCTYPE html>
<html>
<head><title>ProServices Projects</title></head>
<body>
    <h1>Projects</h1>
    <ul>
        @foreach($projects as $p)
            <li>{{ $p->project_code }} - {{ $p->status }}</li>
        @endforeach
    </ul>
</body>
</html>
