<x-layouts.admin title="Response Templates" heading="Support Response Templates">
    <div class="grid-cols-[1fr_22rem] gap-4">
        {{-- Templates list --}}
        <div class="vstack gap-3">
            @forelse($templates as $category => $group)
                <div>
                    <p class="label mb-2 text-uppercase">{{ $category ?: 'General' }}</p>
                    @foreach($group as $t)
                        <div class="card-p mb-2">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div>
                                    <p class="fw-semibold fs-sm text-dark">{{ $t->title }}</p>
                                    <p class="fs-xs text-muted mt-1">{{ $t->body }}</p>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <span class="badge-{{ $t->is_active?'mint':'slate' }} text-xs">{{ $t->is_active?'Active':'Inactive' }}</span>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('admin.support-templates.update',$t) }}" class="mt-2 vstack gap-2" x-data="{ open: false }">
                                @csrf @method('PATCH')
                                <button type="button" @click="open=!open" class="fs-xs text-primary">Edit</button>
                                <div x-show="open" x-cloak class="vstack gap-2 mt-2">
                                    <input name="title" value="{{ $t->title }}" class="input fs-sm" required>
                                    <input name="category" value="{{ $t->category }}" class="input fs-sm" placeholder="Category">
                                    <textarea name="body" rows="4" class="textarea fs-sm" required>{{ $t->body }}</textarea>
                                    <label class="d-flex align-items-center gap-2 fs-sm">
                                        <input type="checkbox" name="is_active" value="1" class="checkbox" @checked($t->is_active)>Active
                                    </label>
                                    <x-button type="submit" size="sm">Save</x-button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @empty
                <x-empty-state icon="📝" title="No templates yet">Add your first response template.</x-empty-state>
            @endforelse
        </div>
        {{-- Create form --}}
        <div>
            <x-card>
                <h2 class="section-title mb-3">New Template</h2>
                <form method="POST" action="{{ route('admin.support-templates.store') }}" class="vstack gap-2">
                    @csrf
                    <x-input name="title" label="Title" required placeholder="e.g. Verification Help" />
                    <div><label class="label">Category</label>
                        <input name="category" class="input fs-sm" placeholder="e.g. verification, payment…"></div>
                    <div><label class="label">Body <span class="text-secondary fs-xs">(use {user_name}, {order_number} etc.)</span></label>
                        <textarea name="body" rows="6" class="textarea fs-sm" required
                            placeholder="Hi {user_name},&#10;Thank you for reaching out…"></textarea></div>
                    <x-button type="submit">Create template</x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.admin>
