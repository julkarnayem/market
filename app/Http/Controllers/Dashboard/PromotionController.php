<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Promotion;
use App\Services\PromotionService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $service) {}

    public function index()
    {
        $user = Auth::user();
        $promotions = Promotion::where('seller_id', $user->id)
            ->with('asset')->latest()->paginate(15);
        return view('dashboard.promotions', compact('promotions'));
    }

    public function create(Request $request)
    {
        $asset = Asset::where('id', $request->asset_id)
            ->where('user_id', Auth::id())
            ->where('status', 'published')
            ->firstOrFail();

        $wallet    = Auth::user()->wallet;
        $prices    = PromotionService::PRICES;
        $activePromo = Promotion::where('asset_id', $asset->id)
            ->where('status','active')->where('ends_at','>',now())->first();

        return view('dashboard.promotions-buy', compact('asset','wallet','prices','activePromo'));
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
