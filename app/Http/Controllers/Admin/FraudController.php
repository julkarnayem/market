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

class FraudController extends Controller
{
    public function __construct(
        private readonly FraudService $fraud,
        private readonly AuditLogger  $audit,
    ) {}

    public function index()
    {
        $this->authorize('fraud.view');
        $status  = request('status','pending');
        $reviews = FraudReview::query()
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->with('user','reviewer')
            ->orderByDesc('risk_score')
            ->paginate(25);
        return view('admin.fraud.index', compact('reviews','status'));
    }

    public function show(User $user)
    {
        $this->authorize('fraud.view');
        $events  = FraudEvent::where('user_id', $user->id)->latest()->limit(50)->get();
        $review  = FraudReview::where('user_id', $user->id)->first();
        return view('admin.fraud.show', compact('user','events','review'));
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
}
