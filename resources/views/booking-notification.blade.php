<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} — {{ $studioName }}</title>
</head>
<body>
    <h1>{{ $heading }}</h1>
    @foreach ($lines as $line)
        <p>{{ $line }}</p>
    @endforeach
    <p><a href="{{ $portalUrl }}">Moj studio</a></p>
    <p>Vaš studio: {{ $studioName }}</p>
</body>
</html>
