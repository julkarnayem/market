<?php
namespace App\Http\Controllers\Dashboard;

use App\Enums\AssetStatus;
use App\Enums\InventoryType;
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
use Inertia\Inertia;

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
        $query    = Auth::user()->listings();
        if ($status !== 'all') $query->where('status', $status);
        $listings = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Dashboard/Listings/Index', [
            'status'  => $status,
            'canSell' => Auth::user()->canSell(),
            'listings' => $listings->through(fn (Asset $l) => [
                'id'                 => $l->id,
                'title'              => $l->title,
                'slug'               => $l->slug,
                'price_formatted'    => Money::format($l->price),
                'quantity'           => $l->quantity,
                'available_quantity' => $l->available_quantity,
                'status'             => $l->status->value,
                'is_featured'        => (bool) $l->is_featured,
                'created_date'       => $l->created_at->format('d M Y'),
            ]),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Asset::class);
        $categories = Category::roots()->active()
            ->with(['children' => fn ($q) => $q->active()
                ->with(['attributes' => fn ($a) => $a->where('is_active', true)->orderBy('position')])])
            ->orderBy('position')->get();

        return Inertia::render('Dashboard/Listings/Create', [
            'feePercent' => number_format($this->settings->sellerFeeBp() / 100, 2),
            'categories' => $categories->map(fn (Category $cat) => [
                'id'            => $cat->id,
                'name'          => $cat->name,
                'icon'          => $cat->icon,
                'is_prohibited' => (bool) $cat->is_prohibited,
                'children'      => $cat->children->map(fn (Category $sub) => [
                    'id'            => $sub->id,
                    'name'          => $sub->name,
                    'is_prohibited' => (bool) $sub->is_prohibited,
                    'is_restricted' => (bool) $sub->is_restricted,
                    'selectable'    => $sub->isSelectable(),
                    'attributes'    => $sub->attributes->map(fn ($attr) => [
                        'id'          => $attr->id,
                        'label'       => $attr->label,
                        'type'        => $attr->type,
                        'is_required' => (bool) $attr->is_required,
                        'unit'        => $attr->unit,
                        'placeholder' => $attr->placeholder,
                        'options'     => $attr->safeOptions(),
                    ])->values(),
                ])->values(),
            ])->values(),
        ]);
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
        $listing->load('category','coverImage','attributeValues.attribute','images','edits');
        $user = Auth::user();

        return Inertia::render('Dashboard/Listings/Show', [
            'listing' => [
                'id'                     => $listing->id,
                'title'                  => $listing->title,
                'slug'                   => $listing->slug,
                'description'            => $listing->description,
                'status'                 => $listing->status->value,
                'is_featured'            => (bool) $listing->is_featured,
                'category_name'          => $listing->category?->name,
                'price_formatted'        => Money::format($listing->price),
                'quantity'               => $listing->quantity,
                'available_quantity'     => $listing->available_quantity,
                'sold_quantity'          => $listing->sold_quantity,
                'created_date'           => $listing->created_at->format('d M Y'),
                'updated_human'          => $listing->updated_at->diffForHumans(),
                'rejection_reason'       => $listing->rejection_reason,
                'changes_requested_note' => $listing->changes_requested_note,
                'cover_url'              => $listing->coverImage?->url() ?? $listing->images->first()?->url(),
                'images'                 => $listing->images->map(fn ($img) => [
                    'id'  => $img->id,
                    'url' => $img->url(),
                ])->values(),
                'attributes'             => $listing->attributeValues->map(fn ($av) => [
                    'id'    => $av->id,
                    'label' => $av->attribute?->label,
                    'value' => $av->value,
                ])->values(),
                'edits'                  => $listing->edits->map(fn ($e) => [
                    'id'     => $e->id,
                    'status' => $e->status,
                    'note'   => $e->review_note,
                    'at'     => $e->created_at->format('d M Y, H:i'),
                ])->values(),
            ],
            'canUpdate'      => $user->can('update', $listing),
            'canTogglePause' => $user->can('togglePause', $listing),
        ]);
    }

    public function edit(Asset $listing)
    {
        $this->authorize('update', $listing);
        $listing->load('category.attributes', 'attributeValues');

        $attributes = $listing->category->attributes
            ->where('is_active', true)->sortBy('position')->values()
            ->map(function ($attr) use ($listing) {
                $current = $listing->attributeValues->firstWhere('category_attribute_id', $attr->id);
                return [
                    'id'          => $attr->id,
                    'label'       => $attr->label,
                    'type'        => $attr->type,
                    'is_required' => (bool) $attr->is_required,
                    'unit'        => $attr->unit,
                    'placeholder' => $attr->placeholder,
                    'options'     => $attr->safeOptions(),
                    'value'       => $current?->value,
                ];
            });

        return Inertia::render('Dashboard/Listings/Edit', [
            'feePercent' => number_format($this->settings->sellerFeeBp() / 100, 2),
            'listing' => [
                'id'              => $listing->id,
                'title'           => $listing->title,
                'description'     => $listing->description,
                'status'          => $listing->status->value,
                'inventory_type'  => $listing->inventoryType()->value,
                'inventory_label' => $listing->inventoryType()->label(),
                'quantity'        => $listing->quantity,
                'price_bdt'       => (string) Money::toBdt($listing->price),
                'price_formatted' => Money::format($listing->price),
                'is_price_locked' => $listing->isPriceLocked(),
            ],
            'attributes' => $attributes,
        ]);
    }

    public function update(UpdateListingRequest $request, Asset $listing)
    {
        if ($listing->status === AssetStatus::Draft) {
            // Direct update — still draft. Quantity is only meaningful for a
            // Multiple listing; a posted qty must not be able to give a Single
            // item a stock of 5, or an Unlimited one a ceiling.
            $quantity = $listing->inventoryType() === InventoryType::Multiple
                ? max(1, (int) $request->quantity)
                : 1;

            $listing->update([
                'title'       => $request->title,
                'description' => $request->description,
                'price'       => Money::toPoisha($request->price_bdt),
                'quantity'    => $quantity,
                'available_quantity' => $quantity,
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
