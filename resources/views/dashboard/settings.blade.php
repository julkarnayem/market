<x-layouts.dashboard title="Account Settings" heading="Account Settings">
    <div class="max-w-xl vstack gap-3">
        {{-- Email address --}}
        <x-card>
            <h2 class="section-title mb-3">Email Address</h2>
            <dl class="fs-sm">
                <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-light">
                    <dt class="text-muted">Current email</dt>
                    <dd class="fw-medium text-dark">{{ auth()->user()->email }}</dd>
                </div>
                <div class="d-flex align-items-center justify-content-between py-2">
                    <dt class="text-muted">Verified</dt>
                    <dd>
                        @if(auth()->user()->hasVerifiedEmail())
                            <span class="badge-mint fs-xs">✓ Verified</span>
                        @else
                            <span class="badge-amber fs-xs">Unverified</span>
                            <form method="POST" action="{{ route('verification.send') }}" class="d-inline ms-2">
                                @csrf
                                <button class="fs-xs text-primary">Resend verification</button>
                            </form>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-card>

        {{-- Notification preferences --}}
        <x-card>
            <h2 class="section-title mb-3">Notification Preferences</h2>
            <div class="vstack gap-2 fs-sm text-muted">
                <div class="d-flex align-items-center justify-content-between py-1">
                    <span>Order updates</span>
                    <span class="badge-mint fs-xs">Always on</span>
                </div>
                <div class="d-flex align-items-center justify-content-between py-1">
                    <span>Offer notifications</span>
                    <span class="badge-mint fs-xs">Always on</span>
                </div>
                <div class="d-flex align-items-center justify-content-between py-1">
                    <span>Promotion expiry warnings</span>
                    <span class="badge-mint fs-xs">Always on</span>
                </div>
                <p class="fs-xs text-secondary pt-2 border-top border-light">Granular notification preferences will be available in a future update.</p>
            </div>
        </x-card>

        {{-- Danger zone --}}
        <x-card>
            <h2 class="section-title text-danger mb-3">Danger Zone</h2>
            <p class="fs-sm text-muted mb-3">Need to close your account? Contact support — we'll process your request within 5 business days.</p>
            <a href="{{ route('dashboard.tickets.create') }}" class="btn-outline text-danger">
                Request account closure
            </a>
        </x-card>
    </div>
</x-layouts.dashboard>
