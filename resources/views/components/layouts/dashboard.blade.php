<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    
    {{-- Bootstrap 5 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    {{-- Custom component styles --}}
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="h-100">
    @include('partials.header')
    <div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4 py-4 pb-5 pb-md-4">
        <div class="grid-cols-[15rem_1fr] gap-lg-5">
            <aside class="d-none d-lg-block">
                @include('partials.dashboard-sidebar')
            </aside>
            <div class="">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h1 class="font-display fs-3 fw-bold text-dark">{{ $heading ?? ($title ?? 'Dashboard') }}</h1>
                    @isset($actions)<div>{{ $actions }}</div>@endisset
                </div>
                {{ $slot }}
            </div>
        </div>
    </div>
    @include('partials.mobile-nav')
    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
