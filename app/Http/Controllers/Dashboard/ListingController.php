<?php
namespace App\Http\Controllers\Dashboard;

use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreListingRequest;
use App\Http\Requests\Dashboard\UpdateListingRequest;
use App\Models\Asset;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Services\ListingService;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListingController extends Controller
{
    public function __construct(
        private readonly ListingService  $listings,
        private readonly SettingsService $settings,
        private readonly AuditLogger     $audit,
    ) {}

    public function index()
    {
        $status   = request('status','all');
        $query    = Auth::user()->listings()->with('category','images');
        if ($status !== 'all') $query->where('status', $status);
        $listings = $query->latest()->paginate(15)->withQueryString();
        return view('dashboard.listings', compact('listings','status'));
    }

    public function create()
    {
        $this->authorize('create', Asset::class);
        $categories = Category::roots()->active()->with(['children' => fn($q) => $q->active()])->orderBy('position')->get();
        $feeBp      = $this->settings->sellerFeeBp();
        return view('dashboard.listings-create', compact('categories','feeBp'));
    }

    public function store(StoreListingRequest $request)
    {
        $isDraft = (bool) $request->input('save_as_draft', false);
        $asset   = $this->listings->create(
            Auth::user(),
            $request->validated(),
            $request->file('images', []),
            $isDraft
        );
        $this->audit->log($isDraft ? 'listing.draft_saved' : 'listing.submitted', $asset);

        $msg = $isDraft
            ? 'Listing saved as draft. Submit it for review when ready.'
            : "Listing submitted for review. You will be notified once approved.";

        return redirect()->route('dashboard.listings')->with('success', $msg);
    }

    public function show(Asset $listing)
    {
        $this->authorize('view', $listing);
        $listing->load('category.attributes','attributeValues.attribute','images','edits.reviewer');
        return view('dashboard.listings-show', compact('listing'));
    }

    public function edit(Asset $listing)
    {
        $this->authorize('update', $listing);
        $listing->load('category.attributes','attributeValues','images');
        $categories = Category::roots()->active()->with(['children' => fn($q) => $q->active()])->orderBy('position')->get();
        $feeBp      = $this->settings->sellerFeeBp();
        return view('dashboard.listings-edit', compact('listing','categories','feeBp'));
    }

    public function update(UpdateListingRequest $request, Asset $listing)
    {
        if ($listing->status === AssetStatus::Draft) {
            // Direct update — still draft
            $listing->update([
                'title'       => $request->title,
                'description' => $request->description,
                'price'       => Money::toPoisha($request->price_bdt),
                'quantity'    => $request->quantity,
                'available_quantity' => $request->quantity,
            ]);
            $this->listings->syncAttributes($listing, $request->input('attributes',[]));
            return redirect()->route('dashboard.listings.show', $listing)->with('success','Draft updated.');
        }

        // Published → create pending edit
        $edit = $this->listings->submitEdit($listing, $request->validated(), []);
        $this->audit->log('listing.edit_submitted', $edit);

        return redirect()->route('dashboard.listings.show', $listing)
            ->with('success','Edit submitted for review. Your live listing is unchanged until approved.');
    }

    public function submitDraft(Asset $listing)
    {
        $this->authorize('update', $listing);
        abort_unless($listing->status === AssetStatus::Draft, 422, 'Not a draft.');
        $listing->update(['status' => AssetStatus::PendingReview, 'policy_accepted_at' => now()]);
        $this->audit->log('listing.submitted', $listing);
        return redirect()->route('dashboard.listings.show', $listing)->with('success','Submitted for review.');
    }

    public function togglePause(Asset $listing)
    {
        $this->authorize('togglePause', $listing);
        $newStatus = $listing->status === AssetStatus::Paused ? AssetStatus::Published : AssetStatus::Paused;
        $listing->update(['status' => $newStatus]);
        $this->audit->log('listing.status_changed', $listing, ['status'=>$listing->status->value], ['status'=>$newStatus->value]);
        return back()->with('success', $newStatus === AssetStatus::Paused ? 'Listing paused.' : 'Listing resumed.');
    }

    public function deleteImage(Asset $listing, \App\Models\AssetImage $image)
    {
        abort_unless($listing->user_id === Auth::id(), 403);
        abort_unless($image->asset_id === $listing->id, 403);
        $image->delete();
        return back()->with('success','Image removed.');
    }

    /** AJAX: live fee calculation */
    public function feePreview(Request $request)
    {
        $price  = Money::toPoisha(max(0, (float)$request->input('price_bdt', 0)));
        $result = $this->listings->sellerEarning($price);
        return response()->json([
            'price'       => Money::format($result['price']),
            'fee_percent' => $result['fee_percent'],
            'fee_amount'  => Money::format($result['fee_amount']),
            'earning'     => Money::format($result['earning']),
        ]);
    }
}
