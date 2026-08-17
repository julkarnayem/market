<?php
namespace App\Http\Controllers\Admin;

use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetEdit;
use App\Models\AssetImage;
use App\Services\AuditLogger;
use App\Services\ListingService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ListingController extends Controller
{
    public function __construct(
        private readonly ListingService $service,
        private readonly AuditLogger $audit,
    ) {}

    public function index()
    {
        $tab   = request('tab', 'pending_review');
        $query = Asset::query()->with('seller', 'category', 'images');
        if ($tab !== 'all') {
            $query->where('status', $tab);
        }
        $listings = $query
            ->when(request('q'), fn ($q) => $q->where('title', 'like', '%'.request('q').'%'))
            ->when(request('seller'), fn ($q) => $q->whereHas('seller', fn ($u) => $u->where('name', 'like', '%'.request('seller').'%')))
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Listings/Index', [
            'listings' => $listings->through(fn (Asset $a) => [
                'id'      => $a->id,
                'title'   => $a->title,
                'seller'  => $a->seller?->name ?? '—',
                'price'   => Money::format($a->price),
                'status'  => $a->status->value,
                'created' => $a->created_at->format('d M Y'),
                'url'     => route('admin.listings.show', $a),
            ]),
            'filters' => ['tab' => $tab],
            'tabs'    => $this->statusTabs(),
        ]);
    }

    public function show(Asset $listing)
    {
        $this->authorize('listings.view');
        $listing->load(
            'seller', 'category.attributes', 'attributeValues.attribute',
            'images', 'edits.requester', 'edits.reviewer',
        );
        $pending = $listing->pendingEdit;

        return Inertia::render('Admin/Listings/Show', [
            'listing' => [
                'id'                     => $listing->id,
                'title'                  => $listing->title,
                'slug'                   => $listing->slug,
                'status'                 => $listing->status->value,
                'price'                  => Money::format($listing->price),
                'quantity'               => $listing->quantity,
                'description'            => $listing->description,
                'category_name'          => $listing->category?->name,
                'seller'                 => $listing->seller?->name ?? '—',
                'seller_verified'        => (bool) $listing->seller?->isVerifiedSeller(),
                'created'                => $listing->created_at->format('d M Y'),
                'marketplace_url'        => route('marketplace.show', $listing->slug),
                'rejection_reason'       => $listing->rejection_reason,
                'changes_requested_note' => $listing->changes_requested_note,
            ],
            'images' => $listing->images->map(fn (AssetImage $img) => [
                'id'  => $img->id,
                'url' => $img->url(),
            ])->all(),
            'attributes' => $listing->attributeValues->map(fn ($av) => [
                'id'    => $av->id,
                'label' => $av->attribute?->label,
                'value' => $av->value,
            ])->all(),
            'pendingEdit' => $pending ? [
                'id'        => $pending->id,
                'old_title' => $pending->old_values['title'] ?? '—',
                'old_price' => Money::format($pending->old_values['price'] ?? 0),
                'new_title' => $pending->new_values['title'] ?? '—',
                'new_price' => Money::format($pending->new_values['price'] ?? 0),
            ] : null,
            'edits' => $listing->edits->map(fn (AssetEdit $e) => [
                'id'        => $e->id,
                'status'    => $e->status,
                'requester' => $e->requester?->name ?? '—',
                'reviewer'  => $e->reviewer?->name,
                'note'      => $e->review_note,
                'at'        => $e->created_at->format('d M Y'),
            ])->all(),
        ]);
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

    /** @return list<array{value:string,label:string}> */
    private function statusTabs(): array
    {
        return [
            ['value' => 'pending_review',        'label' => 'Pending'],
            ['value' => 'published',             'label' => 'Published'],
            ['value' => 'pending_edit_approval', 'label' => 'Edit Pending'],
            ['value' => 'rejected',              'label' => 'Rejected'],
            ['value' => 'suspended',             'label' => 'Suspended'],
        ];
    }
}
