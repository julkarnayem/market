<x-layouts.admin title="Categories" heading="Category Management">
    <x-slot:actions><a href="{{ route('admin.categories.create') }}" class="btn-primary">+ New Category</a></x-slot:actions>

    <div class="vstack gap-3">
        @forelse($categories as $cat)
            <div class="card overflow-hidden">
                <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-light border-bottom border-light">
                    <div class="d-flex align-items-center gap-3">
                        <span class="fs-4">{{ $cat->icon ?? '🗂️' }}</span>
                        <div>
                            <p class="fw-semibold text-dark">{{ $cat->name }}</p>
                            <p class="fs-xs text-muted">{{ $cat->children->count() }} subcategories · pos {{ $cat->position }}</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if($cat->is_prohibited)<span class="badge-rose">Prohibited</span>@elseif($cat->is_restricted)<span class="badge-amber">Restricted</span>@endif
                        @if(!$cat->is_active)<span class="badge-slate">Inactive</span>@endif
                        <a href="{{ route('admin.categories.edit',$cat) }}" class="btn-ghost btn-sm">Edit</a>
                    </div>
                </div>
                @if($cat->children->isNotEmpty())
                    <div class="divide-y">
                        @foreach($cat->children->sortBy('position') as $sub)
                            <div class="d-flex align-items-center justify-content-between px-3 py-2">
                                <div class="d-flex align-items-center gap-3 ms-3">
                                    <span class="fs-sm text-secondary">↳</span>
                                    <span class="fs-sm fw-medium text-dark">{{ $sub->name }}</span>
                                    <span class="badge-slate fs-xs">{{ $sub->attributes->count() }} attrs</span>
                                    @if($sub->is_prohibited)<span class="badge-rose fs-xs">Prohibited</span>@endif
                                    @if(!$sub->is_active)<span class="badge-slate fs-xs">Inactive</span>@endif
                                </div>
                                <a href="{{ route('admin.categories.edit',$sub) }}" class="btn-ghost btn-sm">Edit</a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <x-empty-state icon="🗂️" title="No categories yet">Create your first category to get started.</x-empty-state>
        @endforelse
    </div>
</x-layouts.admin>
