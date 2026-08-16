<x-layouts.dashboard title="Verification" heading="Seller Verification">
    <div class="max-w-2xl vstack gap-3">

        {{-- Status card --}}
        <x-card>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h2 class="section-title">Verification Status</h2>
                    <p class="section-sub">Required before you can create or sell listings.</p>
                </div>
                <x-status-badge :status="$user->verification_status->value" class="fs-sm" />
            </div>
        </x-card>

        @if ($user->verification_status->value === 'approved')
            <x-alert type="success">
                <p class="fw-semibold">You are a verified seller ✓</p>
                <p class="mt-1 fs-sm">Verified on {{ $current?->reviewed_at?->format('d M Y') }}.</p>
            </x-alert>

        @elseif ($user->verification_status->value === 'pending')
            <x-alert type="warning">
                <p class="fw-semibold">Verification under review</p>
                <p class="mt-1 fs-sm">Submitted {{ $current?->submitted_at?->diffForHumans() }}. We review within 1–2 business days.</p>
            </x-alert>

        @else
            @if ($user->verification_status->value === 'rejected' && $current)
                <x-alert type="error">
                    <p class="fw-semibold">Previous submission rejected</p>
                    @if($current->rejection_reason)
                        <p class="mt-1 fs-sm">Reason: {{ $current->rejection_reason }}</p>
                    @endif
                    <p class="fs-sm mt-1">Please submit a new verification below.</p>
                </x-alert>
            @endif

            <x-card>
                <h2 class="section-title mb-3">Submit Verification</h2>

                <form method="POST" action="{{ route('dashboard.verification.submit') }}"
                      enctype="multipart/form-data" class="vstack gap-3"
                      x-data="{ method: 'nid' }">
                    @csrf

                    {{-- Document type selector --}}
                    <div>
                        <label class="label">Document Type <span class="text-danger">*</span></label>
                        <div class="row row-cols-2 gap-2 mt-1">
                            @foreach([
                                ['nid',             '🪪', 'NID',             'Front page + Back page'],
                                ['passport',        '📘', 'Passport',        '1 image required'],
                                ['dob',             '📅', 'Date of Birth',   'DOB + 1 document'],
                                ['driving_license', '🚗', 'Driving License', '1 image required'],
                            ] as [$val, $icon, $label, $hint])
                            <label class="position-relative d-flex align-items-start gap-2 border rounded-3 p-2"
                                   :class="method === '{{ $val }}' ? 'border-green-400 bg-green-50' : 'border-gray-200 hover:border-gray-300'">
                                <input type="radio" name="verification_method" value="{{ $val }}"
                                       x-model="method" class="mt-1 flex-shrink-0" style="accent-color:#10B981">
                                <div>
                                    <p class="fw-medium fs-sm text-dark">{{ $icon }} {{ $label }}</p>
                                    <p class="fs-xs text-secondary mt-1">{{ $hint }}</p>
                                </div>
                            </label>
                            @endforeach
                        </div>
                        @error('verification_method') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- NID: Front + Back (x-if removes from DOM when not selected) --}}
                    <template x-if="method === 'nid'">
                        <div class="vstack gap-2">
                            <div>
                                <label class="label">NID — Front Page <span class="text-danger">*</span></label>
                                <input type="file" name="document_front" accept="image/jpeg,image/png"
                                       class="input @error('document_front') input-error @enderror">
                                <p class="field-hint">Clear photo of the front of your NID card. JPG or PNG, max 10MB.</p>
                                @error('document_front') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">NID — Back Page <span class="text-danger">*</span></label>
                                <input type="file" name="document_back" accept="image/jpeg,image/png"
                                       class="input @error('document_back') input-error @enderror">
                                <p class="field-hint">Clear photo of the back of your NID card. JPG or PNG, max 10MB.</p>
                                @error('document_back') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </template>

                    {{-- Passport: 1 image --}}
                    <template x-if="method === 'passport'">
                        <div>
                            <label class="label">Passport Photo Page <span class="text-danger">*</span></label>
                            <input type="file" name="document_front" accept="image/jpeg,image/png,application/pdf"
                                   class="input @error('document_front') input-error @enderror">
                            <p class="field-hint">Photo of your passport's information page. JPG, PNG or PDF, max 10MB.</p>
                            @error('document_front') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </template>

                    {{-- Date of Birth: DOB input + 1 document --}}
                    <template x-if="method === 'dob'">
                        <div class="vstack gap-2">
                            <div>
                                <label class="label">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"
                                       class="input @error('date_of_birth') input-error @enderror"
                                       max="{{ now()->subYears(18)->format('Y-m-d') }}">
                                @error('date_of_birth') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Supporting Document <span class="text-danger">*</span></label>
                                <input type="file" name="document_front" accept="image/jpeg,image/png,application/pdf"
                                       class="input @error('document_front') input-error @enderror">
                                <p class="field-hint">Birth certificate or official document showing your DOB. JPG, PNG or PDF, max 10MB.</p>
                                @error('document_front') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </template>

                    {{-- Driving License: 1 image --}}
                    <template x-if="method === 'driving_license'">
                        <div>
                            <label class="label">Driving License <span class="text-danger">*</span></label>
                            <input type="file" name="document_front" accept="image/jpeg,image/png,application/pdf"
                                   class="input @error('document_front') input-error @enderror">
                            <p class="field-hint">Clear photo of your driving license. JPG, PNG or PDF, max 10MB.</p>
                            @error('document_front') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </template>

                    <x-button type="submit" size="lg">Submit for Verification</x-button>
                </form>
            </x-card>
        @endif

        {{-- Verification history --}}
        @if ($history->count() > 1 || ($history->count() === 1 && $user->verification_status->value !== 'not_submitted'))
            <x-card>
                <h2 class="section-title mb-2">Verification History</h2>
                <div class="divide-y">
                    @foreach ($history as $v)
                        <div class="py-2 d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <p class="fs-sm fw-medium text-dark">
                                    Attempt #{{ $v->attempt_number }} —
                                    {{ match($v->document_type) {
                                        'nid'             => 'National ID',
                                        'passport'        => 'Passport',
                                        'dob'             => 'Date of Birth',
                                        'driving_license' => 'Driving License',
                                        default           => strtoupper($v->document_type)
                                    } }}
                                </p>
                                <p class="fs-xs text-muted mt-1">Submitted {{ $v->submitted_at?->format('d M Y, H:i') }}</p>
                                @if ($v->rejection_reason)
                                    <p class="fs-xs text-danger mt-1">Reason: {{ $v->rejection_reason }}</p>
                                @endif
                            </div>
                            <x-status-badge :status="$v->status" />
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif
    </div>
</x-layouts.dashboard>
