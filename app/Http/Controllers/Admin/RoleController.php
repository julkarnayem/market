<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        $this->authorize('staff.manage');
        $roles = Role::withCount('users')->with('permissions')->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function edit(Role $role)
    {
        $this->authorize('staff.manage');
        abort_if($role->is_protected, 403, 'This role cannot be edited.');
        $permissions = Permission::orderBy('group')->orderBy('name')->get()->groupBy('group');
        $rolePerms   = $role->permissions->pluck('id')->toArray();
        return view('admin.roles.edit', compact('role','permissions','rolePerms'));
    }

    public function update(Request $request, Role $role)
    {
        $this->authorize('staff.manage');
        abort_if($role->is_protected, 403);
        $data = $request->validate([
            'display_name' => 'required|string|max:100',
            'description'  => 'nullable|string|max:500',
            'permissions'  => 'array',
            'permissions.*'=> 'exists:permissions,id',
        ]);
        $old = $role->permissions->pluck('name')->sort()->implode(',');
        $role->update(['display_name' => $data['display_name'], 'description' => $data['description']]);
        $role->permissions()->sync($data['permissions'] ?? []);
        $new = Permission::whereIn('id', $data['permissions'] ?? [])->pluck('name')->sort()->implode(',');
        $this->audit->log('role.permissions_updated', $role, ['perms'=>$old], ['perms'=>$new], '', 'staff');
        return redirect()->route('admin.roles')->with('success', "Role '{$role->display_name}' updated.");
    }

    public function store(Request $request)
    {
        $this->authorize('staff.manage');
        $data = $request->validate([
            'name'         => 'required|string|unique:roles|max:50|regex:/^[a-z_]+$/',
            'display_name' => 'required|string|max:100',
            'description'  => 'nullable|string|max:500',
        ]);
        $role = Role::create([...$data, 'is_admin_role' => true, 'is_protected' => false]);
        $this->audit->log('role.created', $role, [], ['name' => $role->name], '', 'staff');
        return redirect()->route('admin.roles.edit', $role)->with('success', "Role '{$role->display_name}' created. Now assign permissions.");
    }
}
