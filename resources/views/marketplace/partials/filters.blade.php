<form method="GET" action="{{ route('marketplace.index') }}" id="filter-form">

{{-- Search (desktop only — mobile has its own bar) --}}
<div class="d-none d-lg-block mb-3">
    <label class="label">Search</label>
    <div class="position-relative">
        <span class="position-absolute text-secondary pe-none">⌕</span>
        <input name="q" value="{{ request('q') }}" placeholder="Keyword…" class="input ps-4">
    </div>
</div>

{{-- Categories --}}
<div class="mb-3">
    <p class="label">Category</p>
    <div class="space-y-0.5">
        <a href="{{ route('marketplace.index', request()->except(['category','subcategory','page'])) }}"
           class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm {{ !request('category') ? 'bg-brand-50 text-brand-700 font-medium' : 'text-slate-600 hover:bg-slate-100' }}">
           All categories
        </a>
        @foreach($rootCategories as $cat)
            <div>
                <a href="{{ route('marketplace.index', array_merge(request()->except(['subcategory','page']),['category'=>$cat->slug])) }}"
                   class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium
                          {{ request('category')===$cat->slug ? 'bg-brand-50 text-brand-700' : 'text-slate-700 hover:bg-slate-100' }}">
                    <span>{{ $cat->icon ?? '🗂️' }}</span>{{ $cat->name }}
                </a>
                @if(request('category')===$cat->slug && $cat->children->isNotEmpty())
                    <div class="ms-4 mt-1 space-y-0.5">
                        @foreach($cat->children as $sub)
                            <a href="{{ route('marketplace.index', array_merge(request()->except(['page']),['category'=>$cat->slug,'subcategory'=>$sub->slug])) }}"
                               class="block rounded-lg px-2 py-1 text-xs
                                      {{ request('subcategory')===$sub->slug ? 'bg-brand-50 text-brand-700 font-medium' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100' }}">
                                {{ $sub->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>

{{-- Price --}}
<div class="mb-3">
    <p class="label">Price (৳)</p>
    <div class="row row-cols-2 gap-2">
        <div class="position-relative">
            <span class="position-absolute text-secondary font-mono fs-xs">৳</span>
            <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" class="input ps-4 fs-sm" min="0" step="1">
        </div>
        <div class="position-relative">
            <span class="position-absolute text-secondary font-mono fs-xs">৳</span>
            <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max" class="input ps-4 fs-sm" min="0" step="1">
        </div>
    </div>
</div>

{{-- Toggles --}}
<div class="mb-3 vstack gap-2">
    <label class="d-flex align-items-center gap-2 fs-sm">
        <input type="checkbox" name="verified_only" value="1" class="checkbox" @checked(request()->boolean('verified_only'))>
        <span class="text-dark">Verified sellers only</span>
    </label>
    <label class="d-flex align-items-center gap-2 fs-sm">
        <input type="checkbox" name="featured_only" value="1" class="checkbox" @checked(request()->boolean('featured_only'))>
        <span class="text-dark">Featured only</span>
    </label>
    <label class="d-flex align-items-center gap-2 fs-sm">
        <input type="checkbox" name="in_stock" value="1" class="checkbox" @checked(request()->boolean('in_stock'))>
        <span class="text-dark">In stock only</span>
    </label>
</div>

{{-- Dynamic attribute filters --}}
@if($dynamicAttributes->isNotEmpty())
    <div class="mb-3 pt-3 border-top border-light">
        <p class="label mb-2">{{ $currentSubcategory?->name ?? '' }} Filters</p>
        <div class="vstack gap-2">
        @foreach($dynamicAttributes->where('is_filterable',true) as $attr)
            @php $key = 'attr_'.$attr->id; @endphp
            <div>
                <label class="fs-xs fw-medium text-muted mb-1 d-block">{{ $attr->label }}@if($attr->unit) <span class="text-secondary">({{ $attr->unit }})</span>@endif</label>
                @if(in_array($attr->type,['number','decimal']))
                    <div class="row row-cols-2 gap-1">
                        <input type="number" name="{{ $key }}_min" placeholder="Min" value="{{ request($key.'_min') }}" class="input fs-xs py-1" step="any">
                        <input type="number" name="{{ $key }}_max" placeholder="Max" value="{{ request($key.'_max') }}" class="input fs-xs py-1" step="any">
                    </div>
                @elseif($attr->type==='select' && !empty($attr->options))
                    <select name="{{ $key }}" class="select fs-sm">
                        <option value="">Any</option>
                        @foreach($attr->safeOptions() as $opt)
                            <option value="{{ $opt }}" @selected(request($key)===$opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                @elseif($attr->type==='boolean')
                    <select name="{{ $key }}" class="select fs-sm">
                        <option value="">Any</option>
                        <option value="yes" @selected(request($key)==='yes')>Yes</option>
                        <option value="no"  @selected(request($key)==='no')>No</option>
                    </select>
                @else
                    <input type="text" name="{{ $key }}" value="{{ request($key) }}" class="input fs-sm" placeholder="Filter…">
                @endif
            </div>
        @endforeach
        </div>
    </div>
@endif

{{-- Preserve sort --}}
@if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif

<div class="d-flex gap-2 mt-3">
    <button type="submit" class="btn-primary flex-grow-1 fs-sm py-2">Apply filters</button>
    <a href="{{ route('marketplace.index') }}" class="btn-ghost fs-sm py-2">Clear</a>
</div>
</form>
