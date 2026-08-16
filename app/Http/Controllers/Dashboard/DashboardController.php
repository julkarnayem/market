<?php
namespace App\Http\Controllers\Dashboard;

use App\Enums\AssetStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user   = Auth::user();
        $wallet = $user->wallet;
        $stats  = [
            'available'    => $wallet?->available_balance ?? 0,
            'pending'      => $wallet?->pending_balance ?? 0,
            'listings'     => $user->listings()->where('status', AssetStatus::Published)->count(),
            'orders'       => $user->purchases()->count() + $user->sales()->count(),
            'pending_offers'=> $user->receivedOffers()->where('status','pending')->count(),
            'unread_msgs'  => (function() use ($user) {
                $convIds = $user->conversations()->pluck('conversations.id');
                return Message::whereIn('conversation_id', $convIds)
                    ->where('sender_user_id', '!=', $user->id)
                    ->whereDoesntHave('conversation', fn($q) => $q->whereHas('participants',
                        fn($p) => $p->where('users.id', $user->id)
                                    ->whereRaw('messages.created_at <= conversation_participants.last_read_at')
                    ))->count();
            })(),
        ];
        $recentListings = $user->listings()->with('category')->latest()->limit(5)->get();
        $recentPurchases= $user->purchases()->with('asset')->latest()->limit(5)->get();
        return view('dashboard.index', compact('stats','recentListings','recentPurchases'));
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

    public function section(string $title, string $part = 'the next release')
    {
        return view('dashboard.section', compact('title','part'));
    }
}
