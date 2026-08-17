<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Promotion;
use App\Services\PromotionService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $service) {}

    public function index()
    {
        $user = Auth::user();
        $promotions = Promotion::where('seller_id', $user->id)
            ->with('asset')->latest()->paginate(15);

        return Inertia::render('Dashboard/Promotions', [
            'promotions' => $promotions->through(fn ($p) => [
                'id'               => $p->id,
                'asset_title'      => $p->asset?->title,
                'is_manual'        => (bool) $p->is_manual,
                'days'             => (int) $p->days,
                'amount_display'   => $p->price > 0 ? Money::format($p->price) : '৳0 (free)',
                'amount_formatted' => Money::format((int) $p->price),
                'starts_full'      => $p->starts_at?->format('d M Y, H:i'),
                'ends_full'        => $p->ends_at?->format('d M Y, H:i'),
                'starts_short'     => $p->starts_at?->format('d M'),
                'ends_short'       => $p->ends_at?->format('d M Y'),
                'status'           => $p->status,
            ]),
        ]);
    }

    public function create(Request $request)
    {
        $asset = Asset::where('id', $request->asset_id)
            ->where('user_id', Auth::id())
            ->where('status', 'published')
            ->firstOrFail();

        $wallet      = Auth::user()->wallet;
        $available   = (int) ($wallet?->available_balance ?? 0);
        $activePromo = Promotion::where('asset_id', $asset->id)
            ->where('status', 'active')->where('ends_at', '>', now())->first();

        return Inertia::render('Dashboard/PromotionsBuy', [
            'asset' => [
                'id'              => $asset->id,
                'title'           => $asset->title,
                'category_name'   => $asset->category?->name,
                'cover_url'       => $asset->coverImage?->url(),
                'price_formatted' => Money::format($asset->price),
            ],
            'prices' => collect(PromotionService::PRICES)
                ->map(fn ($poisha, $days) => [
                    'days'            => $days,
                    'price_poisha'    => $poisha,
                    'price_formatted' => Money::format($poisha),
                ])->values(),
            'walletBalance'          => $available,
            'walletBalanceFormatted' => Money::format($available),
            'activePromo'            => $activePromo
                ? ['ends_full' => $activePromo->ends_at->format('d M Y, H:i')]
                : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id' => 'required|integer|exists:assets,id',
            'days'     => 'required|integer|in:1,2,3,4,5',
        ]);

        $asset = Asset::where('id', $data['asset_id'])
            ->where('user_id', Auth::id())->firstOrFail();

        try {
            $promotion = $this->service->purchase($asset, Auth::user(), $data['days']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard.promotions')
            ->with('success', "Promotion active! Your listing will be featured until " . $promotion->ends_at->format('d M Y, H:i'));
    }
}
