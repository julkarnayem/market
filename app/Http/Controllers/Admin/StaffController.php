<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class StaffController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        // Reading the roster is `staff.view` — the permission both sidebars gate
        // the nav item on. Authorizing `staff.manage` here made the menu link
        // 403 for anyone holding view without manage.
        $this->authorize('staff.view');

        $staff = User::whereHas('roles')->with('roles')->latest()->paginate(25);

        return Inertia::render('Admin/Staff/Index', [
            'staff' => $staff->through(fn (User $u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'email'   => $u->email,
                'initial' => mb_strtoupper(mb_substr($u->name, 0, 1)),
                'status'  => $u->status->value,
                'roles'   => $u->roles->pluck('display_name')->all(),
                'joined'  => $u->created_at->format('d M Y'),
                'url'     => route('admin.staff.show', $u),
                // Suspending yourself is blocked server-side (403); hide the button.
                'is_self' => $u->id === Auth::id(),
            ]),
        ]);
    }

    public function create()
    {
        $this->authorize('staff.manage');

        return Inertia::render('Admin/Staff/Create', [
            'roles' => $this->assignableRoles(),
        ]);
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
        $this->authorize('staff.view');
        abort_unless($user->isAdmin(), 404);
        $user->load('roles.permissions');

        $logs = AuditLog::where('user_id', $user->id)->latest('created_at')->limit(20)->get();

        return Inertia::render('Admin/Staff/Show', [
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'status'       => $user->status->value,
                'joined'       => $user->created_at->format('d M Y'),
                'suspended_at' => $user->suspended_at?->format('d M Y, H:i'),
                'is_self'      => $user->id === Auth::id(),
            ],
            // Grouped by role: staff hold exactly one role (assignRole syncs to
            // a single id), but the roster allows several so render them all.
            'role_permissions' => $user->roles->map(fn (Role $role) => [
                'id'           => $role->id,
                'display_name' => $role->display_name,
                'permissions'  => $role->permissions->pluck('name')->sort()->values()->all(),
            ])->values()->all(),
            'roles'           => $this->assignableRoles(),
            'current_role_id' => $user->roles->first()?->id,
            'logs' => $logs->map(fn (AuditLog $log) => [
                'id'     => $log->id,
                'action' => $log->action,
                'at'     => $log->created_at?->format('d M, H:i'),
            ])->values()->all(),
        ]);
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
        $this->forgetSessions($user);
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

    /**
     * Kick a suspended account off every browser it is signed in on.
     *
     * This replaces `$user->tokens()->delete()`: the app never installed
     * Sanctum (User has no HasApiTokens trait), so that call threw
     * BadMethodCallException and suspending any staff account 500'd — leaving
     * the account both un-suspended and still logged in. Sessions are the real
     * credential store here, so purge those instead.
     */
    private function forgetSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return; // file/redis/array stores are not indexed by user_id.
        }

        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
    }

    /**
     * Admin roles offered by the create form and the change-role select.
     *
     * `assignable` mirrors the super_admin escalation guard in store() and
     * assignRole() so the UI can disable an option instead of letting the user
     * pick it and eat a 403.
     */
    private function assignableRoles(): array
    {
        $isSuperAdmin = (bool) Auth::user()?->hasRole('super_admin');

        return Role::where('is_admin_role', true)->orderBy('id')->get()
            ->map(fn (Role $role) => [
                'id'           => $role->id,
                'name'         => $role->name,
                'display_name' => $role->display_name,
                'assignable'   => $role->name !== 'super_admin' || $isSuperAdmin,
            ])->values()->all();
    }
}
