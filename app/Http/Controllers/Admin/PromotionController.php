<?php
namespace App\Http\Controllers\Admin;

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
        $status = request('status', 'active');
        $promotions = Promotion::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with('asset', 'seller')
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Promotions/Index', [
            'promotions' => $promotions->through(fn (Promotion $p) => [
                'id'        => $p->id,
                'listing'   => $p->asset?->title ?? '—',
                'seller'    => $p->seller?->name ?? '—',
                'type'      => $p->is_manual ? 'Admin' : 'Paid',
                'is_manual' => $p->is_manual,
                'days'      => $p->days ?: null,
                'amount'    => $p->price > 0 ? Money::format($p->price) : '—',
                'starts'    => $p->starts_at?->format('d M Y, H:i'),
                'ends'      => $p->ends_at?->format('d M Y, H:i'),
                'status'    => $p->status,
            ]),
            'filters' => ['status' => $status],
            'tabs'    => $this->statusTabs(),
        ]);
    }

    public function feature(Request $request, Asset $asset)
    {
        $this->authorize('promotions.feature');
        $data = $request->validate([
            'ends_at' => 'required|date|after:now',
            'note'    => 'nullable|string|max:500',
        ]);
        $this->service->adminFeature($asset, Auth::user(), \Carbon\Carbon::parse($data['ends_at']), $data['note'] ?? '');
        return back()->with('success', 'Listing featured until ' . \Carbon\Carbon::parse($data['ends_at'])->format('d M Y, H:i'));
    }

    public function unfeature(Promotion $promotion)
    {
        $this->authorize('promotions.feature');
        $this->service->adminUnfeature($promotion, Auth::user());
        return back()->with('success', 'Listing unfeatured.');
    }

    /** @return list<array{value:string,label:string}> */
    private function statusTabs(): array
    {
        return [
            ['value' => 'active',    'label' => 'Active'],
            ['value' => 'expired',   'label' => 'Expired'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
            ['value' => 'all',       'label' => 'All'],
        ];
    }
}
