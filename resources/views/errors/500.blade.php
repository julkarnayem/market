<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>500 · {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    @vite('resources/css/app.css')
</head>
<body class="min-vh-100 bg-light d-grid place-items-center p-4">
    <div class="text-center max-w-md">
        <div class="display-4 mb-3">⚙️</div>
        <p class="text-secondary font-mono fs-sm fw-medium mb-2">500</p>
        <h1 class="font-display fs-3 fw-bold text-dark mb-2">Server Error</h1>
        <p class="text-muted mb-4">Something went wrong on our end. We've been notified and are working on it.</p>
        <div class="d-flex justify-content-center gap-3">
            <a href="{{ url('/') }}" class="btn-primary">Go home</a>
            <button onclick="history.back()" class="btn-outline">Go back</button>
        </div>
    </div>
</body>
</html>
