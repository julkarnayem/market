<?php
namespace App\Http\Controllers\Dashboard;

use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user   = Auth::user();
        $wallet = $user->wallet;

        // Only what the Overview renders. The Blade original also computed
        // pending_offers, unread_msgs, recentListings and recentPurchases and
        // then displayed none of them; those queries are gone rather than
        // shipped to the client unused.
        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'available_formatted' => Money::format((int) ($wallet?->available_balance ?? 0)),
                'pending_formatted'   => Money::format((int) ($wallet?->pending_balance ?? 0)),
                'listings'            => $user->listings()->where('status', AssetStatus::Published)->count(),
                'orders'              => $user->purchases()->count() + $user->sales()->count(),
            ],
        ]);
    }

    public function settings()
    {
        return Inertia::render('Dashboard/Settings', [
            'emailVerified' => Auth::user()->hasVerifiedEmail(),
        ]);
    }

    public function verification()
    {
        $user     = Auth::user();
        $current  = $user->verifications()->latest()->first();
        $history  = $user->verifications()->latest()->get();
        return view('dashboard.verification', compact('user','current','history'));
    }

    public function submitVerification(
        \App\Http\Requests\Dashboard\VerificationRequest $request,
        \App\Services\VerificationService $service,
        \App\Services\AuditLogger $audit
    ) {
        $data     = $request->validated();
        $documentFront = $request->file('document_front');
        $documentBack  = $request->file('document_back');

        $v = $service->submit(Auth::user(), $data, $documentFront, $documentBack);
        $audit->log('verification.submitted', $v, [], ['status'=>'pending']);

        return redirect()->route('dashboard.verification')
            ->with('success', 'Verification submitted. We will review it within 1-2 business days.');
    }

    public function purchases()
    {
        $tab    = request('tab','all');
        $orders = Auth::user()->purchases()
            ->when($tab !== 'all', fn($q) => $q->where('status', $tab))
            ->with(['asset','seller'])->latest()->paginate(15);
        return view('dashboard.purchases', compact('orders','tab'));
    }
}
