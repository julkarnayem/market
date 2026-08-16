<x-layouts.auth title="Login">
<div class="auth-card">
    <div class="text-center mb-4">
        <h1 class="fs-3 fw-bold text-dark" style="font-family:Sora,sans-serif">Login</h1>
        <p class="fs-sm text-muted mt-1">Welcome back to {{ config('app.name') }}</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="vstack gap-3">
        @csrf
        <div>
            <label class="auth-label">Enter your email</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="auth-input @error('email') border-red-400 @enderror"
                   placeholder="Enter your email" required autofocus autocomplete="email">
            @error('email') <p class="auth-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="auth-label">Password</label>
            <input type="password" name="password"
                   class="auth-input @error('password') border-red-400 @enderror"
                   placeholder="Enter your password" required autocomplete="current-password">
            @error('password') <p class="auth-error">{{ $message }}</p> @enderror
        </div>

        <div class="d-flex align-items-center justify-content-between">
            <label class="d-flex align-items-center gap-2 fs-sm text-muted">
                <input type="checkbox" name="remember" class="rounded" style="accent-color:#10B981" checked> Remember me
            </label>
            <a href="{{ route('password.request') }}" class="auth-link fs-sm">Forgot password?</a>
        </div>

        <button type="submit" class="auth-btn">Login</button>
    </form>

    <p class="mt-3 fs-sm text-center text-muted">
        Don't have an account? <a href="{{ route('register') }}" class="auth-link">Signup</a>
    </p>
</div>
</x-layouts.auth>
