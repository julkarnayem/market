<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    
    {{-- Bootstrap 5 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    {{-- Custom component styles --}}
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="h-100 bg-light" x-data="{ sidebar: false }">
    <div class="grid-cols-[16rem_1fr]">
        {{-- Sidebar --}}
        <aside class="d-none d-lg-flex flex-column bg-dark text-slate-300 p-3">
            @include('partials.admin-sidebar')
        </aside>
        {{-- Mobile drawer --}}
        <div x-show="sidebar" x-cloak class="d-lg-none position-fixed top-0 start-0 end-0 bottom-0">
            <div class="position-absolute top-0 start-0 end-0 bottom-0 bg-slate-900/50" @click="sidebar=false"></div>
            <aside class="position-absolute h-100 w-72 bg-dark text-slate-300 p-3 overflow-y-auto">
                @include('partials.admin-sidebar')
            </aside>
        </div>

        <div class="">
            <header class="position-sticky bg-white border-bottom border-secondary border-opacity-25">
                <div class="d-flex h-16 align-items-center gap-3 px-3 px-sm-4">
                    <button @click="sidebar=true" class="btn-ghost d-lg-none">☰</button>
                    <h1 class="font-display fs-5 fw-bold text-dark">{{ $heading ?? ($title ?? 'Admin') }}</h1>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <a href="{{ url('/') }}" class="btn-outline btn-sm">View site</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost btn-sm text-danger">Log out</button></form>
                    </div>
                </div>
            </header>
            <main class="p-3 p-sm-4">
                @include('partials.flash')
                {{ $slot }}
            </main>
        </div>
    </div>
    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
