<x-layouts.public title="Marketplace — Buy & Sell Digital Assets">
<x-slot:head>
    <meta name="description" content="Browse verified digital assets — social accounts, websites, domains and software. Secure BDT payouts, buyer protection.">
</x-slot:head>

<div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4 py-4" x-data="{ filterOpen: false }">

    {{-- Mobile: search + filter bar --}}
    <div class="d-flex align-items-center gap-2 mb-3 d-lg-none">
        <form method="GET" action="{{ route('marketplace.index') }}" class="flex-grow-1 d-flex gap-2">
            @foreach(request()->except(['q','page']) as $k=>$v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <div class="position-relative flex-grow-1">
                <span class="position-absolute text-secondary pe-none">⌕</span>
                <input name="q" value="{{ request('q') }}" placeholder="Search listings…" class="input ps-4 h-10">
            </div>
            <button class="btn-primary btn-sm h-10 px-2">Go</button>
        </form>
        <button @click="filterOpen=true" class="btn-outline btn-sm h-10 d-flex align-items-center gap-1 flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/></svg>
            Filters
        </button>
    </div>

    {{-- Mobile filter drawer --}}
    <div x-show="filterOpen" x-cloak class="position-fixed top-0 start-0 end-0 bottom-0 d-lg-none" x-transition>
        <div class="position-absolute top-0 start-0 end-0 bottom-0 bg-slate-900/50" @click="filterOpen=false"></div>
        <aside class="position-absolute h-100 w-80 max-w-[90vw] bg-white overflow-y-auto shadow-pop">
            <div class="d-flex align-items-center justify-content-between p-3 border-bottom border-light">
                <h2 class="fw-semibold text-dark">Filters</h2>
                <button @click="filterOpen=false" class="btn-ghost btn-icon" aria-label="Close">✕</button>
            </div>
            <div class="p-3">@include('marketplace.partials.filters')</div>
        </aside>
    </div>

    <div class="grid-cols-[17rem_1fr] gap-lg-5">
        {{-- Desktop sidebar --}}
        <aside class="d-none d-lg-block">
            <div class="position-sticky vstack gap-1">
                @include('marketplace.partials.filters')
            </div>
        </aside>

        {{-- Main area --}}
        <div class="">
            {{-- Header row --}}
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3 flex-wrap">
                <div>
                    <h1 class="font-display fw-bold text-dark fs-5">
                        @if(request('q'))"{{ request('q') }}"
                        @elseif($currentSubcategory){{ $currentSubcategory->name }}
                        @elseif($currentCategory){{ $currentCategory->name }}
                        @else Marketplace @endif
                    </h1>
                    <p class="fs-sm text-muted">{{ number_format($assets->total()) }} asset{{ $assets->total()===1?'':'s' }} found</p>
                </div>
                <form method="GET" action="{{ route('marketplace.index') }}" class="d-flex align-items-center gap-2">
                    @foreach(request()->except(['sort','page']) as $k=>$v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <select name="sort" class="select w-auto fs-sm" onchange="this.form.submit()">
                        <option value="newest"     @selected($sort==='newest')>Newest</option>
                        <option value="oldest"     @selected($sort==='oldest')>Oldest</option>
                        <option value="price_asc"  @selected($sort==='price_asc')>Price ↑</option>
                        <option value="price_desc" @selected($sort==='price_desc')>Price ↓</option>
                        <option value="popular"    @selected($sort==='popular')>Most popular</option>
                        <option value="featured"   @selected($sort==='featured')>Featured first</option>
                    </select>
                </form>
            </div>

            @if($assets->isEmpty())
                <x-empty-state icon="🔍" title="No listings found">
                    Try clearing some filters or searching a different keyword.
                    <x-slot:slot>
                        <a href="{{ route('marketplace.index') }}" class="btn-outline mt-3">Clear all filters</a>
                    </x-slot:slot>
                </x-empty-state>
            @else
                <div class="row row-cols-1 row-cols-2 row-cols-3 gap-3">
                    @foreach($assets as $asset)
                        @include('marketplace.partials.asset-card', ['asset'=>$asset, 'isFavorited'=>isset($userFavoriteIds[$asset->id])])
                    @endforeach
                </div>
                <div class="mt-4">{{ $assets->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
</div>
</x-layouts.public>
