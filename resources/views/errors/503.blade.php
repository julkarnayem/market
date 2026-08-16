<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Service Unavailable · {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;min-height:100vh;background:#f8fafc;display:grid;place-items:center;padding:1.5rem}
        .card{max-width:28rem;text-align:center;background:white;border-radius:1.5rem;padding:2.5rem 2rem;box-shadow:0 1px 3px rgba(0,0,0,.1)}
        h1{font-family:'Sora',sans-serif;font-size:1.75rem;font-weight:800;color:#0f172a;margin:.75rem 0 .5rem}
        p{color:#64748b;font-size:.9rem;line-height:1.6;margin-bottom:1.25rem}
        a{color:#4f46e5;font-weight:600;text-decoration:none}
    </style>
</head>
<body>
    <div class="card">
        <div style="font-size:3rem">🔧</div>
        <h1>Maintenance</h1>
        <p>{{ config('app.name') }} is briefly offline for scheduled maintenance. We'll be back shortly.</p>
        <p style="font-size:.8rem;color:#94a3b8">If this persists, contact support.</p>
    </div>
</body>
</html>
