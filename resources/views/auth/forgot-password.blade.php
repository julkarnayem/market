<x-layouts.auth title="Forgot Password">
<div class="auth-card">
    <div class="step-indicator">
        <div class="step-dot step-dot-active">1</div>
        <div class="step-line"></div>
        <div class="step-dot step-dot-inactive">2</div>
        <div class="step-line"></div>
        <div class="step-dot step-dot-inactive">3</div>
    </div>

    <div class="text-center mb-4">
        <h1 class="fs-3 fw-bold text-dark" style="font-family:Sora,sans-serif">Forgot Password</h1>
        <p class="fs-sm text-muted mt-1">Enter your registered mobile number to receive an OTP</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" class="vstack gap-3">
        @csrf
        <div>
            <label class="auth-label">Mobile Number</label>
            <div class="position-relative">
                <span class="position-absolute text-muted fs-sm fw-medium">+880</span>
                <input type="tel" name="phone" value="{{ old('phone') }}"
                       class="auth-input pl-14 @error('phone') border-red-400 @enderror"
                       placeholder="01711234567" required autofocus maxlength="11"
                       pattern="01[3-9]\d{8}">
            </div>
            @error('phone') <p class="auth-error">{{ $message }}</p> @enderror
            <p class="fs-xs text-secondary mt-1">We'll send a 6-digit OTP to reset your password.</p>
        </div>

        <button type="submit" class="auth-btn">Send OTP →</button>
    </form>

    <p class="mt-3 fs-sm text-center text-muted">
        Remember your password? <a href="{{ route('login') }}" class="auth-link">Login</a>
    </p>
</div>
</x-layouts.auth>
