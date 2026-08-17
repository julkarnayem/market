<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudEvent;
use App\Models\FraudReview;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FraudService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FraudController extends Controller
{
    public function __construct(
        private readonly FraudService $fraud,
        private readonly AuditLogger  $audit,
    ) {}

    public function index()
    {
        $this->authorize('fraud.view');
        $status  = request('status', 'pending');
        $reviews = FraudReview::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['user', 'reviewer'])
            ->orderByDesc('risk_score')
            ->paginate(25)->withQueryString();

        return Inertia::render('Admin/Fraud/Index', [
            'reviews' => $reviews->through(fn (FraudReview $r) => [
                'id'         => $r->id,
                'user_name'  => $r->user?->name ?? '—',
                'user_email' => $r->user?->email ?? '—',
                // Detail page is keyed by the *user*, not the review row.
                'user_url'   => $r->user ? route('admin.fraud.show', $r->user) : null,
                'risk_score' => (int) $r->risk_score,
                'flags'      => $this->humanizeFlags($r->risk_flags),
                'status'     => (string) $r->status,
                'reviewer'   => $r->reviewer?->name,
                'updated'    => $r->updated_at?->diffForHumans() ?? '—',
            ]),
            'filters' => ['status' => $status],
            'tabs'    => $this->statusTabs(),
        ]);
    }

    public function show(User $user)
    {
        $this->authorize('fraud.view');
        $events = FraudEvent::where('user_id', $user->id)->latest()->limit(50)->get();
        $review = FraudReview::with('reviewer')->where('user_id', $user->id)->first();

        return Inertia::render('Admin/Fraud/Show', [
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'risk_score'  => (int) $user->risk_score,
                'flags'       => $this->humanizeFlags($user->risk_flags),
                'status'      => $user->status?->value,
                'profile_url' => route('admin.users.show', $user),
            ],
            'events' => $events->map(fn (FraudEvent $e) => [
                'id'           => $e->id,
                'signal'       => str_replace('_', ' ', (string) $e->signal),
                'score_impact' => (int) $e->score_impact,
                'ip'           => $e->ip_address,
                'date'         => $e->created_at?->format('d M Y, H:i') ?? '—',
            ])->all(),
            'review' => $review ? [
                'status'      => (string) $review->status,
                'admin_notes' => $review->admin_notes,
                'reviewer'    => $review->reviewer?->name,
                'reviewed_at' => $review->reviewed_at?->format('d M Y, H:i'),
            ] : null,
        ]);
    }

    public function clear(Request $request, User $user)
    {
        $this->authorize('fraud.manage');
        $data = $request->validate(['admin_notes' => 'required|string|max:1000']);
        $this->fraud->clear($user, $data['admin_notes'], Auth::id());
        $this->audit->log('fraud.cleared', $user, [], ['note' => $data['admin_notes']], '', 'fraud');
        return back()->with('success', "Risk score cleared for {$user->name}.");
    }

    public function restrict(Request $request, User $user)
    {
        $this->authorize('fraud.manage');
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $this->fraud->restrict($user, $data['reason'], Auth::id());
        $this->audit->log('fraud.restricted', $user, [], ['reason' => $data['reason']], '', 'fraud');
        return back()->with('success', "{$user->name} marked as restricted.");
    }

    /**
     * Signal names are snake_case in the DB; render them as words.
     * Guards against a legacy row whose json column was written without the
     * array cast (see the User::$casts fix) and so reads back as a string.
     *
     * @return list<string>
     */
    private function humanizeFlags(mixed $flags): array
    {
        return array_map(
            fn ($f) => str_replace('_', ' ', (string) $f),
            is_array($flags) ? array_values($flags) : [],
        );
    }

    /** @return list<array{value:string,label:string}> */
    private function statusTabs(): array
    {
        return [
            ['value' => 'pending',    'label' => 'Pending'],
            ['value' => 'escalated',  'label' => 'Escalated'],
            ['value' => 'reviewing',  'label' => 'Reviewing'],
            ['value' => 'cleared',    'label' => 'Cleared'],
            ['value' => 'restricted', 'label' => 'Restricted'],
            ['value' => 'all',        'label' => 'All'],
        ];
    }
}
