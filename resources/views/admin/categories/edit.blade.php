<x-layouts.admin :title="'Edit: '.$category->name" heading="Edit Category">
    <x-breadcrumb :items="[['label'=>'Categories','url'=>route('admin.categories')],['label'=>$category->name]]" />
    <div class="grid-cols-[1fr_22rem] gap-4">
        <div class="vstack gap-3">
            <x-card>
                <h2 class="section-title mb-3">Category Details</h2>
                <form method="POST" action="{{ route('admin.categories.update',$category) }}" class="vstack gap-3">
                    @csrf @method('PATCH')
                    <x-input name="name" label="Name" :value="old('name',$category->name)" required />
                    <div>
                        <label class="label">Parent</label>
                        <select name="parent_id" class="select">
                            <option value="">— Top level —</option>
                            @foreach($parents as $p)<option value="{{ $p->id }}" @selected(old('parent_id',$category->parent_id)==$p->id)>{{ $p->name }}</option>@endforeach
                        </select>
                    </div>
                    <x-input name="icon" label="Icon" :value="old('icon',$category->icon)" />
                    <div><label class="label">Description</label>
                        <textarea name="description" rows="2" class="textarea">{{ old('description',$category->description) }}</textarea>
                    </div>
                    <x-input name="position" label="Sort order" type="number" :value="old('position',$category->position)" min="0" />
                    <div class="d-flex flex-wrap gap-3">
                        <label class="d-flex align-items-center gap-2 fs-sm"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active',$category->is_active))> Active</label>
                        <label class="d-flex align-items-center gap-2 fs-sm"><input type="checkbox" name="is_prohibited" value="1" class="checkbox" @checked(old('is_prohibited',$category->is_prohibited))> Prohibited</label>
                        <label class="d-flex align-items-center gap-2 fs-sm"><input type="checkbox" name="is_restricted" value="1" class="checkbox" @checked(old('is_restricted',$category->is_restricted))> Restricted</label>
                    </div>
                    <x-button type="submit">Save changes</x-button>
                </form>
            </x-card>

            {{-- Dynamic attributes --}}
            <x-card>
                <h2 class="section-title mb-3">Dynamic Attributes</h2>
                @if($attrs->isNotEmpty())
                    <div class="table-wrap mb-3">
                        <table class="table">
                            <thead><tr><th>Label</th><th>Key</th><th>Type</th><th>Required</th><th>Active</th><th>Actions</th></tr></thead>
                            <tbody>
                            @foreach($attrs as $attr)
                                <tr>
                                    <td class="fw-medium">{{ $attr->label }}</td>
                                    <td class="font-mono fs-xs">{{ $attr->key }}</td>
                                    <td><span class="badge-slate">{{ $attr->type }}</span></td>
                                    <td>{{ $attr->is_required ? '✓' : '—' }}</td>
                                    <td>{{ $attr->is_active ? '✓' : '—' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.categories.attributes.update',[$category,$attr]) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" name="is_active" value="{{ $attr->is_active?'0':'1' }}" class="btn-ghost btn-sm">
                                                {{ $attr->is_active ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <h3 class="fw-semibold text-dark mb-2 mt-2">Add Attribute</h3>
                <form method="POST" action="{{ route('admin.categories.attributes.store',$category) }}" class="vstack gap-2">
                    @csrf
                    <div class="row row-cols-2 gap-3">
                        <x-input name="label" label="Label" placeholder="e.g. Subscribers" required />
                        <x-input name="key" label="Key (slug)" placeholder="e.g. subscribers" required />
                    </div>
                    <div class="row row-cols-3 gap-3">
                        <div>
                            <label class="label">Type</label>
                            <select name="type" class="select">
                                @foreach(\App\Models\CategoryAttribute::TYPES as $t)
                                    <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-input name="unit" label="Unit (optional)" placeholder="e.g. /month" />
                        <x-input name="position" label="Sort order" type="number" value="0" min="0" />
                    </div>
                    <div>
                        <label class="label">Options (one per line, for select type)</label>
                        <textarea name="options" rows="3" class="textarea fs-sm" placeholder="Option A&#10;Option B&#10;Option C"></textarea>
                    </div>
                    <div class="d-flex gap-3">
                        <label class="d-flex align-items-center gap-2 fs-sm"><input type="checkbox" name="is_required" value="1" class="checkbox"> Required</label>
                        <label class="d-flex align-items-center gap-2 fs-sm"><input type="checkbox" name="is_filterable" value="1" class="checkbox"> Filterable</label>
                    </div>
                    <x-button type="submit" variant="outline">+ Add attribute</x-button>
                </form>
            </x-card>
        </div>
        <div>
            <x-card>
                <h2 class="section-title mb-2">Danger Zone</h2>
                <form method="POST" action="{{ route('admin.categories.deactivate',$category) }}">
                    @csrf @method('PATCH')
                    <x-button type="submit" variant="danger" class="w-100" onclick="return confirm('Deactivate this category?')">Deactivate category</x-button>
                </form>
                <p class="fs-xs text-muted mt-2">Deactivating hides this category from new listings. Historical data is preserved.</p>
            </x-card>
        </div>
    </div>
</x-layouts.admin>
