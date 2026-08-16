<?php
namespace App\Http\Controllers\Admin;

use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetEdit;
use App\Services\AuditLogger;
use App\Services\ListingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListingController extends Controller
{
    public function __construct(
        private readonly ListingService $service,
        private readonly AuditLogger $audit,
    ) {}

    public function index()
    {
        $tab   = request('tab','pending_review');
        $query = Asset::query()->with('seller','category','images');
        if ($tab !== 'all') $query->where('status', $tab);
        $listings = $query->when(request('q'), fn($q) => $q->where('title','like','%'.request('q').'%'))
            ->when(request('seller'), fn($q) => $q->whereHas('seller',fn($u)=>$u->where('name','like','%'.request('seller').'%')))
            ->latest()->paginate(20)->withQueryString();
        return view('admin.listings', compact('listings','tab'));
    }

    public function show(Asset $listing)
    {
        $this->authorize('listings.view');
        $listing->load('seller','category.attributes','attributeValues.attribute','images','edits.requester','edits.reviewer');
        return view('admin.listings-show', compact('listing'));
    }

    public function approve(Request $request, Asset $listing)
    {
        $this->authorize('listings.approve');
        $data = $request->validate(['notes'=>'nullable|string|max:500']);
        $old  = ['status'=>$listing->status->value];
        $listing->update([
            'status'      => AssetStatus::Published,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'admin_notes' => $data['notes'] ?? null,
        ]);
        $this->audit->log('listing.approved', $listing, $old, ['status'=>'published']);
        return back()->with('success','Listing approved and published.');
    }

    public function reject(Request $request, Asset $listing)
    {
        $this->authorize('listings.approve');
        $data = $request->validate(['reason'=>'required|string|max:1000','notes'=>'nullable|string|max:500']);
        $old  = ['status'=>$listing->status->value];
        $listing->update([
            'status'           => AssetStatus::Rejected,
            'rejection_reason' => $data['reason'],
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
            'admin_notes'      => $data['notes'] ?? null,
        ]);
        $this->audit->log('listing.rejected', $listing, $old, ['status'=>'rejected','reason'=>$data['reason']]);
        return back()->with('success','Listing rejected.');
    }

    public function requestChanges(Request $request, Asset $listing)
    {
        $this->authorize('listings.approve');
        $data = $request->validate(['message'=>'required|string|max:2000']);
        $listing->update([
            'status'                  => AssetStatus::Rejected,
            'changes_requested_note'  => $data['message'],
            'reviewed_by'             => Auth::id(),
            'reviewed_at'             => now(),
        ]);
        $this->audit->log('listing.changes_requested', $listing);
        return back()->with('success','Changes requested. Seller has been notified.');
    }

    public function approveEdit(Request $request, AssetEdit $edit)
    {
        $this->authorize('listings.approve');
        $this->service->applyEdit($edit, Auth::user());
        $this->audit->log('listing.edit_approved', $edit);
        return back()->with('success','Edit approved and applied to live listing.');
    }

    public function rejectEdit(Request $request, AssetEdit $edit)
    {
        $this->authorize('listings.approve');
        $data = $request->validate(['reason'=>'required|string|max:500']);
        $this->service->rejectEdit($edit, Auth::user(), $data['reason']);
        $this->audit->log('listing.edit_rejected', $edit);
        return back()->with('success','Edit rejected. Listing restored to published state.');
    }

    public function suspend(Asset $listing)
    {
        $this->authorize('listings.suspend');
        $old = ['status'=>$listing->status->value];
        $listing->update(['status'=>AssetStatus::Suspended]);
        $this->audit->log('listing.suspended', $listing, $old, ['status'=>'suspended']);
        return back()->with('success','Listing suspended.');
    }
}
