<x-layouts.admin :title="'Verify: '.$verification->user->name" heading="Verification Review">
    <x-breadcrumb :items="[['label'=>'Verification','url'=>route('admin.verification')],['label'=>$verification->user->name]]" />
    <div class="grid-cols-[1fr_20rem] gap-4">
        <div class="vstack gap-3">
            <x-card>
                <h2 class="section-title mb-3">Applicant</h2>
                <dl class="row row-cols-2 gap-3 fs-sm">
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Name</dt><dd class="fw-medium">{{ $verification->user->name }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Email</dt><dd>{{ $verification->user->email }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Document Type</dt><dd class="text-capitalize">
                        {{ match($verification->document_type) {
                            'nid'             => 'National ID (NID)',
                            'passport'        => 'Passport',
                            'dob'             => 'Date of Birth',
                            'driving_license' => 'Driving License',
                            default           => strtoupper($verification->document_type)
                        } }}
                    </dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Attempt #</dt><dd>{{ $verification->attempt_number }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Submitted</dt><dd>{{ $verification->submitted_at?->format('d M Y, H:i') }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Status</dt><dd><x-status-badge :status="$verification->status" /></dd></div>
                </dl>
            </x-card>

            {{-- Verification data — all staff can see meta, only super_admin can see documents --}}
            @can('verification.review')
                <x-card>
                    <h2 class="section-title mb-2">Verification Data <span class="badge-rose ms-2">Confidential</span></h2>

                    @if($verification->date_of_birth)
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 fs-sm mb-2">
                            <p class="fw-medium text-warning">Date of Birth</p>
                            <p class="mt-1 text-warning">{{ $verification->date_of_birth->format('d M Y') }}</p>
                        </div>
                    @endif

                    {{-- Document images — SUPER ADMIN ONLY --}}
                    @if($verification->document_path || $verification->document_back_path)
                        @if(auth()->user()->hasRole('super_admin'))
                            <div class="d-flex flex-wrap gap-3 mt-2">
                                @if($verification->document_path)
                                    <a href="{{ route('admin.verification.document', [$verification,'document']) }}"
                                       target="_blank" class="btn-outline btn-sm">📄 View document (front)</a>
                                @endif
                                @if($verification->document_back_path)
                                    <a href="{{ route('admin.verification.document', [$verification,'document_back']) }}"
                                       target="_blank" class="btn-outline btn-sm">📄 View document (back)</a>
                                @endif
                            </div>
                        @else
                            <div class="rounded-3 bg-danger bg-opacity-10 border p-2 fs-sm text-danger mt-2">
                                🔒 Document images are restricted to the platform owner only.
                            </div>
                        @endif
                    @endif
                </x-card>
            @endcan
        </div>

        {{-- Actions sidebar --}}
        @can('verification.review')
            @if($verification->status === 'pending')
                <div class="vstack gap-3">
                    <x-card>
                        <h2 class="section-title mb-2">Approve</h2>
                        <form method="POST" action="{{ route('admin.verification.approve',$verification) }}" class="vstack gap-2">
                            @csrf
                            <div>
                                <label class="label">Admin notes (optional)</label>
                                <textarea name="notes" rows="3" class="textarea" placeholder="Internal notes…"></textarea>
                            </div>
                            <x-button type="submit" variant="success" class="w-100">✓ Approve verification</x-button>
                        </form>
                    </x-card>
                    <x-card>
                        <h2 class="section-title mb-2">Reject</h2>
                        <form method="POST" action="{{ route('admin.verification.reject',$verification) }}" class="vstack gap-2">
                            @csrf
                            <div>
                                <label class="label">Rejection reason <span class="text-danger">*</span></label>
                                <textarea name="reason" rows="3" class="textarea {{ $errors->has('reason')?'input-error':'' }}"
                                          required placeholder="Explain clearly why the verification is rejected…"></textarea>
                                @error('reason')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <x-button type="submit" variant="danger" class="w-100">✕ Reject</x-button>
                        </form>
                    </x-card>
                </div>
            @else
                <x-card>
                    <p class="fs-sm text-muted">
                        This verification has already been <strong>{{ $verification->status }}</strong>
                        by {{ $verification->reviewer?->name ?? 'admin' }}.
                    </p>
                </x-card>
            @endif
        @endcan
    </div>
</x-layouts.admin>
