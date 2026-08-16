<x-layouts.dashboard title="Security" heading="Security">
    <div class="max-w-2xl vstack gap-3">
        <x-card>
            <h2 class="section-title mb-3">Change Password</h2>
            <form method="POST" action="{{ route('dashboard.security.password') }}" class="vstack gap-3">
                @csrf @method('PATCH')
                <x-input name="current_password" type="password" label="Current password" required />
                <x-input name="password" type="password" label="New password" required />
                <x-input name="password_confirmation" type="password" label="Confirm new password" required />
                <x-button type="submit">Update password</x-button>
            </form>
        </x-card>
        <x-card>
            <h2 class="section-title mb-1">Two-Factor Authentication</h2>
            <p class="section-sub mb-2">Adds an extra layer of security to your account.</p>
            <div class="rounded-3 bg-warning bg-opacity-10 text-warning fs-sm px-3 py-2">2FA setup is available in a future release.</div>
        </x-card>
        <x-card>
            <h2 class="section-title mb-1">Login History</h2>
            <p class="section-sub mb-2">Recent sign-ins to your account.</p>
            <p class="fs-sm text-muted">Last login: {{ auth()->user()->last_login_at?->diffForHumans() ?? 'First session' }}</p>
        </x-card>
    </div>
</x-layouts.dashboard>
