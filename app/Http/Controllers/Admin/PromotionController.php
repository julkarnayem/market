<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $service) {}

    public function index()
    {
        $status = request('status','active');
        $promotions = Promotion::query()
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->with('asset','seller','createdBy','featuredBy')
            ->latest()->paginate(20);
        return view('admin.promotions', compact('promotions','status'));
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
}
