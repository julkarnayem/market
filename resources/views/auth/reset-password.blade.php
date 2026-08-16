<x-layouts.auth title="Reset Password">
<div class="auth-card">
    <div class="step-indicator">
        <div class="step-dot step-dot-done">✓</div>
        <div class="step-line step-line-done"></div>
        <div class="step-dot step-dot-done">✓</div>
        <div class="step-line step-line-done"></div>
        <div class="step-dot step-dot-active">3</div>
    </div>

    <div class="text-center mb-4">
        <h1 class="fs-3 fw-bold text-dark" style="font-family:Sora,sans-serif">New Password</h1>
        <p class="fs-sm text-muted mt-1">Create a strong new password for your account</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="vstack gap-3">
        @csrf
        <div>
            <label class="auth-label">New Password</label>
            <input type="password" name="password"
                   class="auth-input @error('password') border-red-400 @enderror"
                   placeholder="Min 6 characters" required autocomplete="new-password">
            @error('password') <p class="auth-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="auth-label">Confirm New Password</label>
            <input type="password" name="password_confirmation"
                   class="auth-input"
                   placeholder="Confirm your password" required autocomplete="new-password">
        </div>

        <button type="submit" class="auth-btn">Reset Password ✓</button>
    </form>
</div>
</x-layouts.auth>
