<x-layouts.dashboard title="Profile" heading="My Profile">
    <div class="max-w-2xl vstack gap-3">
        <x-card>
            <h2 class="section-title mb-3">Public Profile</h2>
            <form method="POST" action="{{ route('dashboard.profile.update') }}" enctype="multipart/form-data" class="vstack gap-3">
                @csrf @method('PATCH')
                <div class="d-flex align-items-center gap-3">
                    <span class="h-16 w-16 d-grid place-items-center rounded-4 bg-primary bg-opacity-25 text-primary fs-3 fw-bold">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span>
                    <div>
                        <label class="btn-outline btn-sm">
                            Change photo <input type="file" name="profile_photo" accept="image/*" class="visually-hidden" onchange="previewAvatar(this)">
                        </label>
                        <p class="fs-xs text-muted mt-1">JPEG, PNG up to 2MB</p>
                    </div>
                </div>
                <x-input name="name" label="Full name" :value="auth()->user()->name" required />
                <x-input name="username" label="Username" :value="auth()->user()->username" required />
                <div>
                    <label class="label">Bio</label>
                    <textarea name="bio" rows="3" class="textarea" placeholder="Tell buyers and sellers about yourself…">{{ auth()->user()->bio ?? '' }}</textarea>
                </div>
                <x-button type="submit">Save changes</x-button>
            </form>
        </x-card>

        <x-card>
            <h2 class="section-title mb-3">Account Information</h2>
            <dl class="vstack gap-2 fs-sm">
                <div class="d-flex justify-content-between py-2 border-bottom border-light"><dt class="text-muted">Email</dt><dd>{{ auth()->user()->email }}</dd></div>
                <div class="d-flex justify-content-between py-2 border-bottom border-light"><dt class="text-muted">Phone</dt><dd>{{ auth()->user()->phone ?? '—' }}</dd></div>
                <div class="d-flex justify-content-between py-2"><dt class="text-muted">Member since</dt><dd>{{ auth()->user()->created_at->format('F Y') }}</dd></div>
            </dl>
        </x-card>
    </div>
</x-layouts.dashboard>

@push('scripts')
<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const imgs = document.querySelectorAll('.avatar-preview');
            imgs.forEach(img => { img.src = e.target.result; img.style.display = 'block'; });
            document.querySelectorAll('.avatar-initials').forEach(el => el.style.display = 'none');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush