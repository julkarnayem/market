<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Offer;
use App\Services\AuditLogger;
use App\Services\OfferService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OfferController extends Controller
{
    public function __construct(
        private readonly OfferService $service,
        private readonly AuditLogger  $audit,
    ) {}

    public function index()
    {
        $tab  = request('tab', 'received');
        $user = Auth::user();

        // Auto-expire stale offers inline
        $this->service->expireStale();

        $query = match ($tab) {
            'sent'     => Offer::where('buyer_user_id', $user->id)->with('asset.coverImage','seller'),
            'accepted' => Offer::where('seller_user_id', $user->id)->where('status','accepted')->with('asset.coverImage','buyer'),
            'rejected' => Offer::where('seller_user_id', $user->id)->where('status','rejected')->with('asset.coverImage','buyer'),
            'expired'  => Offer::where('buyer_user_id', $user->id)->where('status','expired')->with('asset.coverImage','seller'),
            default    => Offer::where('seller_user_id', $user->id)->where('status','pending')->with('asset.coverImage','buyer'),
        };

        $offers = $query->latest()->paginate(15);

        $isSellerParty = in_array($tab, ['sent', 'expired'], true);

        return Inertia::render('Dashboard/Offers', [
            'tab'    => $tab,
            'offers' => $offers->through(fn ($o) => [
                'id'                     => $o->id,
                'asset_slug'             => $o->asset->slug,
                'asset_title'            => $o->asset->title,
                'party_name'             => $isSellerParty ? $o->seller?->name : $o->buyer?->name,
                'amount_formatted'       => Money::format($o->amount),
                'list_price_formatted'   => Money::format($o->asset->price),
                'status'                 => $o->status->value,
                'is_pending'             => $o->isPending(),
                'is_expired'             => $o->isExpired(),
                'time_remaining_seconds' => $o->timeRemainingSeconds(),
                'created_short'          => $o->created_at->format('d M, H:i'),
            ]),
        ]);
    }

    public function create(Request $request)
    {
        $asset = Asset::where('slug', $request->query('asset'))
            ->published()
            ->with('seller','category','coverImage')
            ->firstOrFail();

        abort_if($asset->user_id === Auth::id(), 403, 'You cannot offer on your own listing.');
        abort_if($asset->isSoldOut(), 422, 'This listing is sold out.');

        $userActiveOffer = Offer::where('asset_id', $asset->id)
            ->where('buyer_user_id', Auth::id())
            ->where('status','pending')
            ->where('expires_at','>',now())
            ->first();

        return Inertia::render('Dashboard/OffersCreate', [
            'asset' => [
                'id'                 => $asset->id,
                'slug'               => $asset->slug,
                'title'              => $asset->title,
                'category_name'      => $asset->category?->name,
                'category_icon'      => $asset->category?->icon,
                'cover_url'          => $asset->coverImage?->url(),
                'price_formatted'    => Money::format($asset->price),
                'quantity'           => (int) $asset->quantity,
                'available_quantity' => (int) $asset->available_quantity,
            ],
            'userActiveOffer' => $userActiveOffer ? [
                'amount_formatted' => Money::format($userActiveOffer->amount),
                'expires_human'    => $userActiveOffer->expires_at->diffForHumans(),
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id'      => 'required|integer|exists:assets,id',
            'amount_bdt'    => 'required|numeric|min:1|max:9999999',
            'quantity'      => 'required|integer|min:1|max:999',
            'buyer_message' => 'nullable|string|max:500',
        ]);

        $asset       = Asset::findOrFail($data['asset_id']);
        $amountPoisha = Money::toPoisha($data['amount_bdt']);

        $offer = $this->service->create(Auth::user(), $asset, $amountPoisha, $data['quantity'], $data['buyer_message'] ?? null);
        $this->audit->log('offer.created', $offer);

        return redirect()->route('dashboard.offers', ['tab'=>'sent'])
            ->with('success', 'Offer submitted. The seller has ' . now()->diffForHumans($offer->expires_at, true) . ' to respond.');
    }

    public function accept(Request $request, Offer $offer)
    {
        $this->authorize('respond', $offer);
        $this->service->enforceExpiry($offer);
        $this->service->accept($offer, Auth::user());
        $this->audit->log('offer.accepted', $offer);

        return back()->with('success', 'Offer accepted. Buyer has been notified to complete payment.');
    }

    public function reject(Request $request, Offer $offer)
    {
        $this->authorize('respond', $offer);
        $this->service->reject($offer, Auth::user());
        $this->audit->log('offer.rejected', $offer);

        return back()->with('success', 'Offer rejected.');
    }
}
