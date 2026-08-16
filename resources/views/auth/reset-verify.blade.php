<x-layouts.auth title="Verify OTP">
<div class="auth-card">
    <div class="step-indicator">
        <div class="step-dot step-dot-done">✓</div>
        <div class="step-line step-line-done"></div>
        <div class="step-dot step-dot-active">2</div>
        <div class="step-line"></div>
        <div class="step-dot step-dot-inactive">3</div>
    </div>

    <div class="text-center mb-4">
        <h1 class="fs-3 fw-bold text-dark" style="font-family:Sora,sans-serif">Verify OTP</h1>
        <p class="fs-sm text-muted mt-1">
            Enter the 6-digit code sent to<br>
            <span class="fw-semibold text-dark">{{ session('reset_phone') }}</span>
        </p>
    </div>

    <form method="POST" action="{{ route('password.verify-otp') }}" class="vstack gap-3">
        @csrf
        <div>
            <label class="auth-label text-center d-block">Enter 6-digit OTP</label>
            <input type="text" name="otp" inputmode="numeric" maxlength="6" pattern="\d{6}"
                   class="otp-input @error('otp') border-red-400 @enderror"
                   placeholder="• • • • • •" required autofocus>
            @error('otp') <p class="auth-error text-center">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="auth-btn">Verify OTP →</button>
    </form>

    <div class="mt-3 text-center">
        <a href="{{ route('password.request') }}" class="fs-sm text-muted">← Change number</a>
    </div>
    <p class="mt-2 fs-xs text-center text-secondary">OTP is valid for 10 minutes. Cannot be resent before expiry.</p>
    <p class="mt-1 fs-xs text-center text-red-400 fw-medium">⚠️ 2 wrong attempts = 24 hour block</p>
</div>
</x-layouts.auth>
