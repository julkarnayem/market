<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        // Reading the matrix is `roles.view` — what both sidebars gate the nav
        // item on. It used to authorize `staff.manage`, leaving the dedicated
        // roles.* permissions unused and the menu link 403-ing for any role that
        // held roles.view without staff.manage.
        $this->authorize('roles.view');

        $roles = Role::withCount('users')->with('permissions')->orderBy('id')->get();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles->map(fn (Role $role) => [
                'id'           => $role->id,
                'name'         => $role->name,
                'display_name' => $role->display_name,
                'description'  => $role->description,
                'is_protected' => (bool) $role->is_protected,
                'users_count'  => (int) $role->users_count,
                'permissions'  => $role->permissions->pluck('name')->sort()->values()->all(),
                // A protected role has no edit page at all (edit() aborts 403).
                'edit_url'     => $role->is_protected ? null : route('admin.roles.edit', $role),
            ])->values()->all(),
        ]);
    }

    public function edit(Role $role)
    {
        $this->authorize('roles.manage');
        abort_if($role->is_protected, 403, 'This role cannot be edited.');

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id'           => $role->id,
                'name'         => $role->name,
                'display_name' => $role->display_name,
                'description'  => $role->description,
                'users_count'  => $role->users()->count(),
            ],
            'groups'   => $this->permissionGroups(),
            'assigned' => $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $this->authorize('roles.manage');
        abort_if($role->is_protected, 403);
        $data = $request->validate([
            'display_name'  => 'required|string|max:100',
            'description'   => 'nullable|string|max:500',
            'permissions'   => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);
        $old = $role->permissions->pluck('name')->sort()->implode(',');
        $role->update([
            'display_name' => $data['display_name'],
            // Nullable and only present when the client sends it.
            'description'  => $data['description'] ?? null,
        ]);
        $role->permissions()->sync($data['permissions'] ?? []);
        $new = Permission::whereIn('id', $data['permissions'] ?? [])->pluck('name')->sort()->implode(',');
        $this->audit->log('role.permissions_updated', $role, ['perms' => $old], ['perms' => $new], '', 'staff');

        return redirect()->route('admin.roles')->with('success', "Role '{$role->display_name}' updated.");
    }

    public function store(Request $request)
    {
        $this->authorize('roles.manage');
        $data = $request->validate([
            'name'         => 'required|string|unique:roles|max:50|regex:/^[a-z_]+$/',
            'display_name' => 'required|string|max:100',
            'description'  => 'nullable|string|max:500',
        ]);
        $role = Role::create([...$data, 'is_admin_role' => true, 'is_protected' => false]);
        $this->audit->log('role.created', $role, [], ['name' => $role->name], '', 'staff');

        return redirect()->route('admin.roles.edit', $role)
            ->with('success', "Role '{$role->display_name}' created. Now assign permissions.");
    }

    /**
     * Every permission, grouped by its `group` column, for the Edit checkboxes.
     *
     * @return list<array{group:string,label:string,permissions:list<array{id:int,name:string}>}>
     */
    private function permissionGroups(): array
    {
        return Permission::orderBy('group')->orderBy('name')->get()
            ->groupBy('group')
            ->map(fn (Collection $perms, string $group) => [
                'group'       => $group,
                'label'       => ucfirst(str_replace('_', ' ', $group)),
                'permissions' => $perms->map(fn (Permission $p) => [
                    'id'   => (int) $p->id,
                    'name' => $p->name,
                ])->values()->all(),
            ])->values()->all();
    }
}
