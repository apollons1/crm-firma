<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Plată confirmată — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f8fafc; color: #0f172a; }
        .card { text-align: center; padding: 2.5rem; max-width: 28rem; }
        .icon { font-size: 3rem; color: #16a34a; }
        h1 { font-size: 1.25rem; margin: 1rem 0 0.5rem; }
        p { color: #475569; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#10003;</div>
        <h1>Mulțumim! Plata a fost înregistrată.</h1>
        <p>Vei primi în curând confirmarea. Poți închide această pagină.</p>
    </div>
</body>
</html>
