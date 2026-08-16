<x-layouts.auth title="Complete Signup">
<div class="auth-card">
    {{-- Steps --}}
    <div class="step-indicator">
        <div class="step-dot step-dot-done">✓</div>
        <div class="step-line step-line-done"></div>
        <div class="step-dot step-dot-done">✓</div>
        <div class="step-line step-line-done"></div>
        <div class="step-dot step-dot-active">3</div>
    </div>

    <div class="text-center mb-4">
        <h1 class="fs-3 fw-bold text-dark" style="font-family:Sora,sans-serif">Complete Signup</h1>
        <p class="fs-sm text-muted mt-1">Almost done! Fill in your details.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="vstack gap-3">
        @csrf
        <div class="row row-cols-2 gap-3">
            <div>
                <label class="auth-label">First Name</label>
                <input type="text" name="first_name" value="{{ old('first_name') }}"
                       class="auth-input @error('first_name') border-red-400 @enderror"
                       placeholder="First name" required autofocus>
                @error('first_name') <p class="auth-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="auth-label">Last Name</label>
                <input type="text" name="last_name" value="{{ old('last_name') }}"
                       class="auth-input @error('last_name') border-red-400 @enderror"
                       placeholder="Last name" required>
                @error('last_name') <p class="auth-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="auth-label">Email Address</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="auth-input @error('email') border-red-400 @enderror"
                   placeholder="Enter your email" required autocomplete="email">
            @error('email') <p class="auth-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="auth-label">Password</label>
            <input type="password" name="password"
                   class="auth-input @error('password') border-red-400 @enderror"
                   placeholder="Create a password (min 8 chars)" required autocomplete="new-password">
            @error('password') <p class="auth-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="auth-label">Confirm Password</label>
            <input type="password" name="password_confirmation"
                   class="auth-input"
                   placeholder="Confirm your password" required autocomplete="new-password">
        </div>

        <button type="submit" class="auth-btn">Create Account ✓</button>
    </form>

    <p class="mt-3 fs-xs text-center text-secondary">
        By signing up you agree to our
        <a href="{{ route('legal','terms') }}" class="auth-link">Terms</a> and
        <a href="{{ route('legal','privacy') }}" class="auth-link">Privacy Policy</a>.
    </p>
</div>
</x-layouts.auth>
