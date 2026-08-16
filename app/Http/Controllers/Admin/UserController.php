<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        $this->authorize('users.view');
        $users = User::query()
            ->doesntHave('roles')                      // marketplace users only
            ->when(request('q'), fn($q) => $q->where(fn($u) =>
                $u->where('name','like','%'.request('q').'%')
                  ->orWhere('email','like','%'.request('q').'%')
                  ->orWhere('phone','like','%'.request('q').'%')
                  ->orWhere('username','like','%'.request('q').'%')))
            ->when(request('status'), fn($q) => $q->where('status',request('status')))
            ->when(request('verification'), fn($q) => $q->where('verification_status',request('verification')))
            ->withCount(['listings','purchases','sales'])
            ->latest()->paginate(25);

        return view('admin.users', compact('users'));
    }

    public function show(User $user)
    {
        $this->authorize('users.view');
        $user->load('wallet','roles','verifications');
        $recentOrders = $user->purchases()->with('asset','seller')->latest()->limit(5)->get();
        $recentSales  = $user->sales()->with('asset','buyer')->latest()->limit(5)->get();
        $tickets      = $user->supportTickets()->latest()->limit(5)->get();
        return view('admin.users-show', compact('user','recentOrders','recentSales','tickets'));
    }

    public function suspend(Request $request, User $user)
    {
        $this->authorize('users.suspend');
        $data = $request->validate(['reason' => 'required|string|max:500']);
        abort_if($user->isAdmin(), 403, 'Use staff management to suspend staff accounts.');
        $user->update(['status' => 'suspended', 'suspended_at' => now(), 'admin_notes' => $data['reason']]);
        $this->audit->log('user.suspended', $user, ['status'=>'active'], ['status'=>'suspended'], $data['reason'], 'user');
        return back()->with('success', "User {$user->name} suspended.");
    }

    public function restore(User $user)
    {
        $this->authorize('users.suspend');
        $user->update(['status' => 'active', 'suspended_at' => null]);
        $this->audit->log('user.restored', $user, ['status'=>'suspended'], ['status'=>'active'], '', 'user');
        return back()->with('success', "User {$user->name} restored.");
    }

    public function note(Request $request, User $user)
    {
        $this->authorize('users.edit');
        $data = $request->validate(['admin_notes' => 'required|string|max:2000']);
        $user->update(['admin_notes' => $data['admin_notes']]);
        $this->audit->log('user.note_updated', $user, [], [], '', 'user');
        return back()->with('success', 'Admin note saved.');
    }
}
