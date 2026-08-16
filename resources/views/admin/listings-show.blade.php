<x-layouts.admin :title="$listing->title" heading="Review Listing">
    <x-breadcrumb :items="[['label'=>'Listings','url'=>route('admin.listings')],['label'=>$listing->title]]" />
    <div class="grid-cols-[1fr_22rem] gap-4">
        <div class="vstack gap-3">
            {{-- Images --}}
            @if($listing->images->isNotEmpty())
                <div class="card overflow-hidden"><div class="row row-cols-4 gap-1 p-2">
                    @foreach($listing->images as $img)
                        <img src="{{ $img->url() }}" class="rounded-3 object-fit-cover w-100">
                    @endforeach
                </div></div>
            @endif
            <x-card>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <x-status-badge :status="$listing->status->value" />
                    <span class="badge-slate">{{ $listing->category->name }}</span>
                    @if($listing->seller->isVerifiedSeller())<span class="badge-mint">✓ Verified seller</span>@endif
                </div>
                <h1 class="font-display fs-4 fw-bold">{{ $listing->title }}</h1>
                <div class="d-flex gap-3 mt-2 fs-sm text-muted">
                    <span>Seller: <strong>{{ $listing->seller->name }}</strong></span>
                    <x-money :amount="$listing->price" class="fw-semibold text-dark" />
                    <span>Qty: {{ $listing->quantity }}</span>
                </div>
                <div class="mt-3 prose prose-sm prose-slate max-w-none">{{ $listing->description }}</div>
            </x-card>
            @if($listing->attributeValues->isNotEmpty())
                <x-card>
                    <h2 class="section-title mb-2">Attributes</h2>
                    <dl class="row row-cols-2 gap-3">
                        @foreach($listing->attributeValues as $av)
                            <div class="rounded-3 bg-light p-2">
                                <dt class="fs-xs text-muted">{{ $av->attribute?->label }}</dt>
                                <dd class="fs-sm fw-medium">{{ $av->value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>
            @endif
            {{-- Pending edit diff --}}
            @if($pendingEdit = $listing->pendingEdit)
                <div class="card border border-2">
                    <div class="bg-warning bg-opacity-10 px-3 py-2 border-bottom"><h2 class="fw-semibold text-warning">⚠ Pending Edit — review changes</h2></div>
                    <div class="p-3 row row-cols-2 gap-3 fs-sm">
                        <div><h3 class="fw-semibold text-muted mb-2">Current (live)</h3>
                            <p class="fw-medium">{{ $pendingEdit->old_values['title'] }}</p>
                            <p class="money text-dark">{{ \App\Support\Money::format($pendingEdit->old_values['price']) }}</p>
                        </div>
                        <div><h3 class="fw-semibold text-primary mb-2">Proposed changes</h3>
                            <p class="fw-medium">{{ $pendingEdit->new_values['title'] }}</p>
                            <p class="money text-primary">{{ \App\Support\Money::format($pendingEdit->new_values['price']) }}</p>
                        </div>
                    </div>
                    @can('listings.approve')
                        <div class="d-flex gap-3 px-3 pb-3">
                            <form method="POST" action="{{ route('admin.listings.approve-edit',$pendingEdit) }}">@csrf
                                <x-button type="submit" variant="success" size="sm">Approve edit</x-button>
                            </form>
                            <form method="POST" action="{{ route('admin.listings.reject-edit',$pendingEdit) }}" class="d-flex gap-2">
                                @csrf
                                <input name="reason" placeholder="Rejection reason" class="input fs-sm" required>
                                <x-button type="submit" variant="danger" size="sm">Reject edit</x-button>
                            </form>
                        </div>
                    @endcan
                </div>
            @endif
            {{-- Edit history --}}
            @if($listing->edits->isNotEmpty())
                <x-card>
                    <h2 class="section-title mb-2">Edit History</h2>
                    <div class="divide-y">
                        @foreach($listing->edits as $edit)
                            <div class="py-2 d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <x-status-badge :status="$edit->status" />
                                    <p class="fs-xs text-muted mt-1">By {{ $edit->requester->name }} · {{ $edit->created_at->format('d M Y') }}</p>
                                    @if($edit->review_note)<p class="fs-xs text-muted">Note: {{ $edit->review_note }}</p>@endif
                                </div>
                                <span class="fs-xs text-secondary">{{ $edit->reviewer?->name }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
        {{-- Actions sidebar --}}
        @can('listings.approve')
        <div class="vstack gap-3">
            @if(in_array($listing->status->value,['pending_review']))
                <x-card>
                    <h2 class="section-title mb-2">Approve</h2>
                    <form method="POST" action="{{ route('admin.listings.approve',$listing) }}" class="vstack gap-2">@csrf
                        <textarea name="notes" rows="2" class="textarea fs-sm" placeholder="Admin notes (optional)…"></textarea>
                        <x-button type="submit" variant="success" class="w-100">✓ Approve & Publish</x-button>
                    </form>
                </x-card>
                <x-card>
                    <h2 class="section-title mb-2">Reject</h2>
                    <form method="POST" action="{{ route('admin.listings.reject',$listing) }}" class="vstack gap-2">@csrf
                        <textarea name="reason" rows="3" class="textarea text-sm {{ $errors->has('reason')?'input-error':'' }}" required placeholder="Rejection reason…"></textarea>
                        @error('reason')<p class="field-error">{{ $message }}</p>@enderror
                        <x-button type="submit" variant="danger" class="w-100">✕ Reject</x-button>
                    </form>
                </x-card>
                <x-card>
                    <h2 class="section-title mb-2">Request Changes</h2>
                    <form method="POST" action="{{ route('admin.listings.request-changes',$listing) }}" class="vstack gap-2">@csrf
                        <textarea name="message" rows="4" class="textarea fs-sm" required placeholder="Describe what the seller needs to fix or improve…"></textarea>
                        <x-button type="submit" variant="warning" class="w-100">↩ Request changes</x-button>
                    </form>
                </x-card>
            @elseif($listing->status->value==='published')
                <x-card>
                    <form method="POST" action="{{ route('admin.listings.suspend',$listing) }}">@csrf
                        <x-button type="submit" variant="danger" class="w-100">Suspend listing</x-button>
                    </form>
                </x-card>
            @endif
        </div>
        @endcan
    </div>
</x-layouts.admin>
