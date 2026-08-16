<x-layouts.dashboard :title="$listing->title">
    <x-breadcrumb :items="[['label'=>'My Listings','url'=>route('dashboard.listings')],['label'=>$listing->title]]" />

    <div class="grid-cols-[1fr_18rem] gap-4">
        {{-- Main --}}
        <div class="vstack gap-3">
            {{-- Images --}}
            @if($listing->images->isNotEmpty())
                <div class="card overflow-hidden">
                    <div class="bg-light position-relative">
                        <img src="{{ $listing->coverImage?->url() ?? $listing->images->first()->url() }}"
                             class="w-100 h-100 object-fit-cover" alt="{{ $listing->title }}">
                    </div>
                    @if($listing->images->count() > 1)
                        <div class="d-flex gap-2 p-2 overflow-x-auto">
                            @foreach($listing->images as $img)
                                <img src="{{ $img->url() }}" class="h-16 w-16 rounded-3 object-fit-cover flex-shrink-0">
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <x-card>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <x-status-badge :status="$listing->status->value" />
                    @if($listing->is_featured)<span class="badge-amber">⭐ Featured</span>@endif
                    <span class="badge-slate">{{ $listing->category->name }}</span>
                </div>
                <h1 class="font-display fs-4 fw-bold text-dark">{{ $listing->title }}</h1>
                <div class="mt-2 d-flex align-items-center gap-3 fs-sm text-muted">
                    <span>Created {{ $listing->created_at->format('d M Y') }}</span>
                    <span>Qty: {{ $listing->available_quantity }}/{{ $listing->quantity }}</span>
                </div>
                <div class="mt-3 prose prose-sm prose-slate max-w-none">{{ $listing->description }}</div>
            </x-card>

            {{-- Attributes --}}
            @if($listing->attributeValues->isNotEmpty())
                <x-card>
                    <h2 class="section-title mb-2">Attributes</h2>
                    <dl class="row row-cols-2 gap-3">
                        @foreach($listing->attributeValues as $av)
                            <div class="rounded-3 bg-light p-2">
                                <dt class="fs-xs text-muted">{{ $av->attribute?->label }}</dt>
                                <dd class="fs-sm fw-medium text-dark mt-1">{{ $av->value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>
            @endif

            {{-- Admin feedback --}}
            @if($listing->rejection_reason)
                <x-alert type="error"><div><p class="fw-semibold">Rejection reason</p><p class="mt-1 fs-sm">{{ $listing->rejection_reason }}</p></div></x-alert>
            @endif
            @if($listing->changes_requested_note)
                <x-alert type="warning"><div><p class="fw-semibold">Changes requested by admin</p><p class="mt-1 fs-sm">{{ $listing->changes_requested_note }}</p></div></x-alert>
            @endif

            {{-- Edit history --}}
            @if($listing->edits->isNotEmpty())
                <x-card>
                    <h2 class="section-title mb-2">Edit History</h2>
                    <div class="divide-y">
                        @foreach($listing->edits as $edit)
                            <div class="py-2">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <x-status-badge :status="$edit->status" />
                                    <span class="fs-xs text-secondary">{{ $edit->created_at->format('d M Y, H:i') }}</span>
                                </div>
                                @if($edit->review_note)<p class="fs-xs text-muted mt-1">Note: {{ $edit->review_note }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>

        {{-- Sidebar --}}
        <aside class="vstack gap-3">
            <x-card>
                <p class="fs-xs text-muted mb-1">Listing Price</p>
                <x-money :amount="$listing->price" class="fs-3 fw-bold text-dark d-block" />
                <div class="mt-2 pt-2 border-top border-light vstack gap-2">
                    @can('update', $listing)
                        <a href="{{ route('dashboard.listings.edit',$listing) }}" class="btn-outline w-100">✏️ Edit listing</a>
                    @endcan
                    @can('togglePause', $listing)
                        <form method="POST" action="{{ route('dashboard.listings.pause',$listing) }}">
                            @csrf
                            <button class="w-full {{ $listing->status->value==='paused'?'btn-outline':'btn-ghost' }}">
                                {{ $listing->status->value==='paused' ? '▶ Resume listing' : '❙❙ Pause listing' }}
                            </button>
                        </form>
                    @endcan
                    @if($listing->status->value === 'draft')
                        <form method="POST" action="{{ route('dashboard.listings.submit',$listing) }}">
                            @csrf
                            <x-button type="submit" class="w-100">Submit for review</x-button>
                        </form>
                    @endif
                    @if($listing->status->value === 'published')
                        <a href="{{ route('marketplace.show',$listing->slug) }}" class="btn-outline w-100" target="_blank">🔗 View live listing</a>
                    @endif
                </div>
            </x-card>
            <x-card>
                <dl class="fs-sm vstack gap-2">
                    <div class="d-flex justify-content-between"><dt class="text-muted">Status</dt><dd><x-status-badge :status="$listing->status->value" /></dd></div>
                    <div class="d-flex justify-content-between"><dt class="text-muted">Sold</dt><dd>{{ $listing->sold_quantity }}</dd></div>
                    <div class="d-flex justify-content-between"><dt class="text-muted">Available</dt><dd>{{ $listing->available_quantity }}</dd></div>
                    <div class="d-flex justify-content-between"><dt class="text-muted">Updated</dt><dd class="fs-xs">{{ $listing->updated_at->diffForHumans() }}</dd></div>
                </dl>
            </x-card>
        </aside>
    </div>
</x-layouts.dashboard>
