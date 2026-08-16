<x-layouts.auth title="Verify email">
    <h1 class="font-display fs-3 fw-bold text-dark">Verify your email</h1>
    <p class="fs-sm text-muted mt-2">We sent a verification link to your inbox. Click it to activate your account.</p>
    @if (session('status') === 'verification-link-sent')
        <div class="mt-3"><x-alert type="success">A new verification link has been sent.</x-alert></div>
    @endif
    <form method="POST" action="{{ route('verification.send') }}" class="mt-4">
        @csrf
        <x-button type="submit" class="w-100">Resend verification email</x-button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-2">
        @csrf
        <button class="w-100 btn-ghost">Log out</button>
    </form>
</x-layouts.auth>
