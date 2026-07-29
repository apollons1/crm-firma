<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Plată anulată — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f8fafc; color: #0f172a; }
        .card { text-align: center; padding: 2.5rem; max-width: 28rem; }
        .icon { font-size: 3rem; color: #dc2626; }
        h1 { font-size: 1.25rem; margin: 1rem 0 0.5rem; }
        p { color: #475569; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#10005;</div>
        <h1>Plata a fost anulată.</h1>
        <p>Nu s-a efectuat nicio tranzacție. Poți reîncerca folosind linkul primit.</p>
    </div>
</body>
</html>
