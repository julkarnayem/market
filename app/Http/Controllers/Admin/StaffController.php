<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        $this->authorize('staff.manage');
        $staff = User::whereHas('roles')->with('roles')->latest()->paginate(25);
        $roles = Role::where('is_admin_role', true)->get();
        return view('admin.staff.index', compact('staff','roles'));
    }

    public function create()
    {
        $this->authorize('staff.manage');
        $roles = Role::where('is_admin_role', true)->get();
        return view('admin.staff.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $this->authorize('staff.manage');
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:10|confirmed',
            'role_id'  => 'required|exists:roles,id',
        ]);
        $role = Role::findOrFail($data['role_id']);

        // Prevent privilege escalation: only super_admin can create super_admin
        if ($role->name === 'super_admin' && !Auth::user()->hasRole('super_admin')) {
            abort(403, 'Only Super Admin can create Super Admin accounts.');
        }

        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'email_verified_at' => now(),
            'status'            => 'active',
        ]);
        $user->roles()->attach($role);

        $this->audit->log('staff.created', $user, [], ['role' => $role->name], '', 'staff');
        return redirect()->route('admin.staff')->with('success', "Staff account created for {$user->name}.");
    }

    public function show(User $user)
    {
        $this->authorize('staff.manage');
        abort_unless($user->isAdmin(), 404);
        $user->load('roles.permissions');
        $roles = Role::where('is_admin_role', true)->get();
        $logs  = \App\Models\AuditLog::where('user_id', $user->id)->latest('created_at')->limit(20)->get();
        return view('admin.staff.show', compact('user','roles','logs'));
    }

    public function assignRole(Request $request, User $user)
    {
        $this->authorize('staff.manage');
        abort_unless($user->isAdmin(), 404);
        $data = $request->validate(['role_id' => 'required|exists:roles,id']);
        $role = Role::findOrFail($data['role_id']);

        // Protect last super_admin
        if ($user->hasRole('super_admin') && $role->name !== 'super_admin') {
            $superAdminCount = User::whereHas('roles', fn($q)=>$q->where('name','super_admin'))->count();
            abort_if($superAdminCount <= 1, 403, 'Cannot demote the last Super Admin.');
        }
        if ($role->name === 'super_admin' && !Auth::user()->hasRole('super_admin')) {
            abort(403, 'Only Super Admin can assign Super Admin role.');
        }

        $old = $user->roles->pluck('name')->implode(',');
        $user->roles()->sync([$role->id]);
        $this->audit->log('staff.role_changed', $user, ['roles'=>$old], ['roles'=>$role->name], '', 'staff');
        return back()->with('success', "Role updated to {$role->display_name}.");
    }

    public function suspend(User $user)
    {
        $this->authorize('staff.manage');
        abort_unless($user->isAdmin(), 404);
        abort_if($user->id === Auth::id(), 403, 'You cannot suspend yourself.');

        $user->update(['status' => 'suspended', 'suspended_at' => now()]);
        // Invalidate sessions
        $user->tokens()->delete();
        $this->audit->log('staff.suspended', $user, [], [], '', 'staff');
        return back()->with('success', "{$user->name} suspended.");
    }

    public function restore(User $user)
    {
        $this->authorize('staff.manage');
        abort_unless($user->isAdmin(), 404);
        $user->update(['status' => 'active', 'suspended_at' => null]);
        $this->audit->log('staff.restored', $user, [], [], '', 'staff');
        return back()->with('success', "{$user->name} restored.");
    }
}
