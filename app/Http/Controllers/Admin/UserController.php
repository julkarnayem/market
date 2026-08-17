<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        $this->authorize('users.view');

        $status       = request('status', 'all');
        $verification = request('verification', 'all');

        $users = User::query()
            ->doesntHave('roles')                      // marketplace users only
            ->when(request('q'), fn($q) => $q->where(fn($u) =>
                $u->where('name','like','%'.request('q').'%')
                  ->orWhere('email','like','%'.request('q').'%')
                  ->orWhere('phone','like','%'.request('q').'%')
                  ->orWhere('username','like','%'.request('q').'%')))
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($verification !== 'all', fn($q) => $q->where('verification_status', $verification))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users->through(fn (User $u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'initial'      => strtoupper(substr($u->name, 0, 1)),
                'status'       => $u->status?->value ?? '—',
                'verification' => $u->verification_status?->value ?? '—',
                'joined'       => $u->created_at->format('d M Y'),
                'last_login'   => $u->last_login_at?->diffForHumans() ?? '—',
                'url'          => route('admin.users.show', $u),
            ]),
            'filters' => [
                'q'            => (string) request('q', ''),
                'status'       => $status,
                'verification' => $verification,
            ],
            'statuses' => array_map(
                fn ($s) => ['value' => $s, 'label' => ucfirst($s)],
                ['active', 'suspended', 'restricted'],
            ),
            'verifications' => [
                ['value' => 'approved',      'label' => 'Verified'],
                ['value' => 'pending',       'label' => 'Pending'],
                ['value' => 'not_submitted', 'label' => 'Not submitted'],
            ],
        ]);
    }

    public function show(User $user)
    {
        $this->authorize('users.view');
        $user->load('wallet')->loadCount(['listings', 'purchases', 'sales']);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'username'     => $user->username,
                'email'        => $user->email,
                'phone'        => $user->phone ?? '—',
                'joined'       => $user->created_at->format('d M Y'),
                'last_login'   => $user->last_login_at?->diffForHumans() ?? '—',
                'status'       => $user->status?->value ?? '—',
                'verification' => $user->verification_status?->value ?? '—',
                // Staff accounts are managed elsewhere; the suspend action 403s on
                // them (see suspend()), so the UI hides the control for admins.
                'is_admin'     => $user->isAdmin(),
            ],
            'wallet' => [
                'available_formatted' => Money::format((int) ($user->wallet?->available_balance ?? 0)),
                'pending_formatted'   => Money::format((int) ($user->wallet?->pending_balance ?? 0)),
            ],
            'counts' => [
                'listings'  => $user->listings_count,
                'purchases' => $user->purchases_count,
                'sales'     => $user->sales_count,
            ],
        ]);
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
