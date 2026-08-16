<x-layouts.admin title="New Category" heading="Create Category">
    <div class="max-w-xl">
        <x-card>
            <form method="POST" action="{{ route('admin.categories.store') }}" class="vstack gap-3">
                @csrf
                <x-input name="name" label="Name" required autofocus />
                <div>
                    <label class="label">Parent Category (leave empty for top-level)</label>
                    <select name="parent_id" class="select">
                        <option value="">— Top level category —</option>
                        @foreach($parents as $p)<option value="{{ $p->id }}" @selected(old('parent_id')==$p->id)>{{ $p->name }}</option>@endforeach
                    </select>
                </div>
                <x-input name="icon" label="Icon (emoji or icon code)" placeholder="📱" />
                <div>
                    <label class="label">Description</label>
                    <textarea name="description" rows="2" class="textarea" placeholder="Optional description…">{{ old('description') }}</textarea>
                </div>
                <x-input name="position" label="Sort order" type="number" :value="old('position',0)" min="0" />
                <div class="d-flex flex-wrap gap-3">
                    <label class="d-flex align-items-center gap-2 fs-sm"><input type="checkbox" name="is_active" value="1" class="checkbox" checked> Active</label>
                    <label class="d-flex align-items-center gap-2 fs-sm"><input type="checkbox" name="is_prohibited" value="1" class="checkbox"> Prohibited</label>
                    <label class="d-flex align-items-center gap-2 fs-sm"><input type="checkbox" name="is_restricted" value="1" class="checkbox"> Restricted</label>
                </div>
                <div class="d-flex gap-3">
                    <x-button type="submit">Create category</x-button>
                    <x-button variant="outline" :href="route('admin.categories')">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
