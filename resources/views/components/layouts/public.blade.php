<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Title --}}
    <title>{{ isset($title) ? $title.' — '.config('app.name') : config('app.name').' — Buy & Sell Digital Assets' }}</title>

    {{-- Meta description --}}
    @if(isset($description))
        <meta name="description" content="{{ Str::limit($description, 160) }}">
    @endif

    {{-- Canonical URL --}}
    @if(isset($canonical))
        <link rel="canonical" href="{{ $canonical }}">
    @else
        <link rel="canonical" href="{{ url()->current() }}">
    @endif

    {{-- Open Graph --}}
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ isset($title) ? $title.' — '.config('app.name') : config('app.name') }}">
    @if(isset($description))<meta property="og:description" content="{{ Str::limit($description, 200) }}">@endif
    @if(isset($ogImage))<meta property="og:image" content="{{ $ogImage }}">@endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ isset($title) ? $title.' — '.config('app.name') : config('app.name') }}">
    @if(isset($description))<meta name="twitter:description" content="{{ Str::limit($description, 200) }}">@endif
    @if(isset($ogImage))<meta name="twitter:image" content="{{ $ogImage }}">@endif

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

    
    {{-- Tailwind CDN covers any classes missing from compiled build --}}
    {{-- Page-specific head content --}}
    {{ $head ?? '' }}

    {{-- Structured data --}}
    {{ $structuredData ?? '' }}
    {{-- Bootstrap 5 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    {{-- Custom component styles --}}
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="h-100 d-flex flex-column">
    @include('partials.header')
    <main class="flex-grow-1 w-100 pb-5 pb-md-0">{{ $slot }}</main>
    @include('partials.footer')
    @include('partials.mobile-nav')
    @stack('scripts')
    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
