<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Services\DisputeService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DisputeController extends Controller
{
    public function __construct(private readonly DisputeService $service) {}

    public function index()
    {
        $status   = request('status','open');
        $disputes = Dispute::query()
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->with('order.buyer','order.seller','opener')
            ->latest()->paginate(20);
        return view('admin.disputes.index', compact('disputes','status'));
    }

    public function show(Dispute $dispute)
    {
        $dispute->load('order.buyer','order.seller','order.asset','opener','resolver','evidence.submitter');
        return view('admin.disputes.show', compact('dispute'));
    }

    public function fullRefund(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $this->service->resolveFullRefund($dispute, Auth::user(), $data['note'] ?? '');
        return back()->with('success', 'Full refund processed. Buyer has been credited.');
    }

    public function partialRefund(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate([
            'refund_bdt' => 'required|numeric|min:0.01',
            'note'       => 'nullable|string|max:1000',
        ]);
        $refundPoisha = Money::toPoisha($data['refund_bdt']);
        $this->service->resolvePartialRefund($dispute, Auth::user(), $refundPoisha, $data['note'] ?? '');
        return back()->with('success', 'Partial refund (' . Money::format($refundPoisha) . ') processed.');
    }

    public function releaseToSeller(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $this->service->resolveSellerRelease($dispute, Auth::user(), $data['note'] ?? '');
        return back()->with('success', 'Seller earning released. Order marked as completed.');
    }

    public function updateStatus(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate([
            'status' => 'required|in:under_review,waiting_for_buyer,waiting_for_seller',
            'note'   => 'nullable|string|max:500',
        ]);
        $this->service->updateStatus($dispute, Auth::user(), $data['status'], $data['note'] ?? '');
        return back()->with('success', 'Dispute status updated.');
    }
}
