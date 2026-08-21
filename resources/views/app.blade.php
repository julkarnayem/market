<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Fonts — matches the existing design system (Sora / Inter / JetBrains Mono) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

    {{-- No @routes here: it inlined the 18 KB route table *and* a 21 KB copy of
         Ziggy's route.umd.js into every full page load, while HandleInertiaRequests
         already shares the same table as the `ziggy` prop. app.ts installs the
         route() global from the bundle instead. --}}

    {{-- Vite: Vue 3 + Inertia client entry (imports resources/css/app.css) --}}
    @vite(['resources/js/app.ts'])

    {{-- Admin theme-color overrides (App\Support\ThemeColors via AppServiceProvider).
         After @vite so it wins over the app.css :root defaults by source order;
         only emitted when an admin has customized a role. CSP allows inline style. --}}
    @if (!empty($themeCss))
        <style id="theme-overrides">:root { {!! $themeCss !!} }</style>
    @endif

    {{-- Per-page <title>, meta description, canonical, Open Graph, Twitter Card, JSON-LD
         are injected here by Inertia's <Head> component (SSR-rendered). --}}
    @inertiaHead
</head>
<body class="min-h-full bg-slate-50 font-sans text-slate-800 antialiased">
    @inertia
</body>
</html>
