<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Account' }} · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
    <style>
    .auth-card { background:#fff;border:1px solid #E5E7EB;border-radius:1rem;padding:2rem;width:100%;max-width:26rem;box-shadow:0 4px 24px rgba(0,0,0,.06); }
    .auth-input { width:100%;padding:.75rem 1rem;border:1.5px solid #E5E7EB;border-radius:.625rem;font-size:.9375rem;color:#111827;outline:none;transition:border-color .15s; }
    .auth-input:focus { border-color:#10B981;box-shadow:0 0 0 3px rgba(16,185,129,.15); }
    .auth-btn { width:100%;padding:.875rem;border-radius:.625rem;font-size:.9375rem;font-weight:600;background:#10B981;color:#fff;border:none;cursor:pointer;transition:background .15s; }
    .auth-btn:hover { background:#059669; }
    .auth-label { display:block;font-size:.875rem;font-weight:500;color:#374151;margin-bottom:.375rem; }
    .auth-error { font-size:.8125rem;color:#EF4444;margin-top:.25rem; }
    .auth-link { color:#10B981;font-weight:600;text-decoration:none; }
    .auth-link:hover { color:#059669;text-decoration:underline; }
    .otp-input { width:100%;padding:1rem;border:1.5px solid #E5E7EB;border-radius:.625rem;font-size:1.5rem;font-weight:700;text-align:center;letter-spacing:.5rem;outline:none;transition:border-color .15s; }
    .otp-input:focus { border-color:#10B981;box-shadow:0 0 0 3px rgba(16,185,129,.15); }
    .step-indicator { display:flex;align-items:center;gap:.5rem;margin-bottom:1.5rem; }
    .step-dot { width:2rem;height:2rem;border-radius:9999px;display:grid;place-items:center;font-size:.75rem;font-weight:700; }
    .step-dot-active { background:#10B981;color:#fff; }
    .step-dot-done { background:#D1FAE5;color:#065F46; }
    .step-dot-inactive { background:#F3F4F6;color:#9CA3AF; }
    .step-line { flex:1;height:2px;background:#E5E7EB;border-radius:9999px; }
    .step-line-done { background:#10B981; }
    </style>
    {{-- Bootstrap 5 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    {{-- Custom component styles --}}
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="d-flex flex-column" style="background:#F9FAFB">
    @include('partials.header')

    <main class="flex-grow-1 d-flex align-items-center justify-content-center px-3 py-5">
        @include('partials.flash')
        {{ $slot }}
    </main>

    @include('partials.footer')
    @include('partials.mobile-nav')
    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
