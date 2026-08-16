<x-layouts.admin :title="'Edit '.$role->display_name" heading="Edit Role Permissions">
    <x-breadcrumb :items="[['label'=>'Roles','url'=>route('admin.roles')],['label'=>$role->display_name]]" />
    <div class="max-w-3xl">
        <x-card>
            <h2 class="section-title mb-1">{{ $role->display_name }}</h2>
            <p class="section-sub mb-3">Toggle permissions for this role. Changes are audited.</p>
            <form method="POST" action="{{ route('admin.roles.update',$role) }}" class="vstack gap-3">
                @csrf @method('PATCH')
                <div class="row row-cols-2 gap-3 mb-2">
                    <div><label class="label">Display Name</label>
                        <input name="display_name" value="{{ $role->display_name }}" class="input" required></div>
                    <div><label class="label">Description</label>
                        <input name="description" value="{{ $role->description }}" class="input"></div>
                </div>

                @foreach($permissions as $group => $perms)
                    <div>
                        <p class="fs-xs fw-bold text-uppercase text-muted mb-2">{{ ucfirst($group) }}</p>
                        <div class="row row-cols-2 row-cols-3 gap-2">
                            @foreach($perms as $perm)
                                <label class="d-flex align-items-center gap-2 fs-sm p-2 rounded-3">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="checkbox"
                                           @checked(in_array($perm->id, $rolePerms))>
                                    <span class="text-dark">{{ $perm->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="d-flex gap-3 pt-3 border-top border-light">
                    <x-button type="submit">Save permissions</x-button>
                    <x-button variant="outline" :href="route('admin.roles')">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
