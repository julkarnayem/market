@php $r = Route::currentRouteName(); @endphp
<nav class="d-md-none position-fixed bg-white/95 border-top border-secondary border-opacity-25 pb-[env(safe-area-inset-bottom)]" aria-label="Mobile navigation">
    <div class="row row-cols-5 h-16">
        <a href="{{ url('/') }}" class="flex flex-col items-center justify-center gap-0.5 text-[11px] {{ $r==='home'?'text-brand-700':'text-slate-500' }}" aria-label="Home">
            <span class="fs-5" aria-hidden="true">🏠</span>Home
        </a>
        <a href="{{ route('marketplace.index') }}" class="flex flex-col items-center justify-center gap-0.5 text-[11px] {{ str_starts_with((string)$r,'marketplace')?'text-brand-700':'text-slate-500' }}" aria-label="Browse">
            <span class="fs-5" aria-hidden="true">🧭</span>Browse
        </a>
        @auth
            <a href="{{ route('dashboard.messages') }}" class="flex flex-col items-center justify-center gap-0.5 text-[11px] {{ $r==='dashboard.messages'?'text-brand-700':'text-slate-500' }}" aria-label="Messages">
                <span class="fs-5" aria-hidden="true">✉️</span>Messages
            </a>
            <a href="{{ route('dashboard.wallet') }}" class="flex flex-col items-center justify-center gap-0.5 text-[11px] {{ $r==='dashboard.wallet'?'text-brand-700':'text-slate-500' }}" aria-label="Wallet">
                <span class="fs-5" aria-hidden="true">👛</span>Wallet
            </a>
            <a href="{{ route('dashboard') }}" class="flex flex-col items-center justify-center gap-0.5 text-[11px] {{ $r==='dashboard'?'text-brand-700':'text-slate-500' }}" aria-label="Account">
                <span class="fs-5" aria-hidden="true">▦</span>Account
            </a>
        @else
            <a href="{{ route('faq') }}" class="d-flex flex-column align-items-center justify-content-center gap-1 text-muted" aria-label="Help"><span class="fs-5" aria-hidden="true">❔</span>Help</a>
            <a href="{{ route('login') }}" class="d-flex flex-column align-items-center justify-content-center gap-1 text-muted" aria-label="Log in"><span class="fs-5" aria-hidden="true">→</span>Log in</a>
            <a href="{{ route('register') }}" class="d-flex flex-column align-items-center justify-content-center gap-1 text-primary" aria-label="Sign up"><span class="fs-5" aria-hidden="true">＋</span>Sign up</a>
        @endauth
    </div>
</nav>
