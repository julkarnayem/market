<?php
namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Services\ViewTrackingService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MarketplaceController extends Controller
{
    use \App\Http\Controllers\Concerns\MapsMarketplaceProps;

    private const ALLOWED_SORTS = [
        'newest', 'oldest', 'price_asc', 'price_desc', 'popular', 'featured',
    ];

    /** Labels for the sort <select>, keyed by the ALLOWED_SORTS values. */
    private const SORT_OPTIONS = [
        'newest'     => 'Newest',
        'oldest'     => 'Oldest',
        'price_asc'  => 'Price ↑',
        'price_desc' => 'Price ↓',
        'popular'    => 'Most popular',
        'featured'   => 'Featured first',
    ];

    public function index(Request $request)
    {
        // Load filter meta
        $rootCategories = Cache::remember('cats_roots', 300, fn() =>
            Category::roots()->active()->with(['children' => fn($q) => $q->active()->with('attributes')])->orderBy('position')->get()
        );

        // Resolve current category/subcategory
        $currentCategory    = null;
        $currentSubcategory = null;
        $dynamicAttributes  = collect();

        if ($request->filled('subcategory')) {
            $currentSubcategory = Category::where('slug', $request->subcategory)->active()->first();
            $currentCategory    = $currentSubcategory?->parent;
            $dynamicAttributes  = $currentSubcategory?->attributes()->active()->orderBy('position')->get() ?? collect();
        } elseif ($request->filled('category')) {
            $currentCategory   = Category::where('slug', $request->category)->active()->first();
            $dynamicAttributes = collect(); // show after subcategory selected
        }

        // Build base query
        $query = Asset::published()
            ->with(['category', 'seller', 'coverImage'])
            ->withCount('favorites');

        // Search
        if ($request->filled('q')) {
            $kw = '%' . $request->q . '%';
            $query->where(fn($q) =>
                $q->where('title', 'like', $kw)
                  ->orWhere('description', 'like', $kw)
            );
        }

        // Category filter
        if ($currentSubcategory) {
            $query->where('category_id', $currentSubcategory->id);
        } elseif ($currentCategory) {
            $ids = $currentCategory->children->pluck('id')->push($currentCategory->id);
            $query->whereIn('category_id', $ids);
        }

        // Price filter (BDT input → poisha)
        if ($request->filled('min_price') && is_numeric($request->min_price)) {
            $query->where('price', '>=', Money::toPoisha($request->min_price));
        }
        if ($request->filled('max_price') && is_numeric($request->max_price)) {
            $query->where('price', '<=', Money::toPoisha($request->max_price));
        }

        // Verified seller filter
        if ($request->boolean('verified_only')) {
            $query->whereHas('seller', fn($q) => $q->where('verification_status', 'approved'));
        }

        // Featured filter
        if ($request->boolean('featured_only')) {
            $query->featuredNow();
        }

        // Availability filter
        if ($request->boolean('in_stock')) {
            $query->where('available_quantity', '>', 0);
        }

        // Dynamic attribute filters (EAV — safe, parameterized)
        foreach ($dynamicAttributes as $attr) {
            $key = 'attr_' . $attr->id;
            if (!$request->filled($key)) continue;

            $val = $request->input($key);
            if (in_array($attr->type, ['number','decimal'])) {
                $minKey = $key . '_min';
                $maxKey = $key . '_max';
                if ($request->filled($minKey) && is_numeric($request->$minKey)) {
                    $query->whereHas('attributeValues', fn($q) =>
                        $q->where('category_attribute_id', $attr->id)
                          ->whereRaw('CAST(value AS DECIMAL(20,4)) >= ?', [$request->$minKey])
                    );
                }
                if ($request->filled($maxKey) && is_numeric($request->$maxKey)) {
                    $query->whereHas('attributeValues', fn($q) =>
                        $q->where('category_attribute_id', $attr->id)
                          ->whereRaw('CAST(value AS DECIMAL(20,4)) <= ?', [$request->$maxKey])
                    );
                }
            } else {
                $query->whereHas('attributeValues', fn($q) =>
                    $q->where('category_attribute_id', $attr->id)
                      ->where('value', 'like', '%' . $val . '%')
                );
            }
        }

        // Sorting — whitelist only
        $sort = in_array($request->sort, self::ALLOWED_SORTS) ? $request->sort : 'newest';
        $query = match ($sort) {
            'oldest'     => $query->oldest(),
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'popular'    => $query->orderByDesc('views_count')->orderByDesc('favorites_count'),
            'featured'   => $query->orderByDesc('is_featured')->latest(),
            default      => $query->latest(),
        };

        $assets = $query->paginate(12)->withQueryString();

        // User favorites for current page (avoid N+1)
        $userFavoriteIds = auth()->check()
            ? auth()->user()->favorites()->whereIn('asset_id', $assets->pluck('id'))->pluck('asset_id')->flip()
            : collect();

        return Inertia::render('Marketplace/Index', [
            // through() maps each item while preserving the paginator, so the
            // client still receives data/links/total for <Pagination>.
            'assets'         => $assets->through(self::mapAsset($userFavoriteIds->all())),
            'rootCategories' => $rootCategories->map(fn (Category $cat) => [
                'slug'     => $cat->slug,
                'name'     => $cat->name,
                'icon'     => $cat->icon,
                'children' => $cat->children->map(fn (Category $sub) => [
                    'slug' => $sub->slug,
                    'name' => $sub->name,
                ])->values()->all(),
            ])->values()->all(),
            'currentCategory'    => $currentCategory ? [
                'slug' => $currentCategory->slug,
                'name' => $currentCategory->name,
            ] : null,
            'currentSubcategory' => $currentSubcategory ? [
                'slug' => $currentSubcategory->slug,
                'name' => $currentSubcategory->name,
            ] : null,
            // Only filterable attributes reach the client — the rest are for the
            // listing form, not for filtering.
            'dynamicAttributes'  => $dynamicAttributes
                ->where('is_filterable', true)
                ->map(fn (CategoryAttribute $attr) => [
                    'key'     => 'attr_'.$attr->id,
                    'label'   => $attr->label,
                    'type'    => $attr->type,
                    'unit'    => $attr->unit,
                    'options' => $attr->safeOptions(),
                ])->values()->all(),
            // Echoed back so the Vue filter form is controlled by the URL, which
            // keeps back/forward navigation and shared links correct.
            'filters'     => self::currentFilters($request, $sort, $dynamicAttributes),
            'sortOptions' => self::SORT_OPTIONS,
        ]);
    }

    /**
     * The filter state as the server understood it, echoed back to the client.
     */
    private static function currentFilters(Request $request, string $sort, $dynamicAttributes): array
    {
        $attributes = [];
        foreach ($dynamicAttributes as $attr) {
            foreach (['attr_'.$attr->id, 'attr_'.$attr->id.'_min', 'attr_'.$attr->id.'_max'] as $key) {
                if ($request->filled($key)) {
                    $attributes[$key] = (string) $request->input($key);
                }
            }
        }

        return [
            'q'             => $request->input('q'),
            'category'      => $request->input('category'),
            'subcategory'   => $request->input('subcategory'),
            'min_price'     => $request->input('min_price'),
            'max_price'     => $request->input('max_price'),
            'verified_only' => $request->boolean('verified_only'),
            'featured_only' => $request->boolean('featured_only'),
            'in_stock'      => $request->boolean('in_stock'),
            'sort'          => $sort,
            'attributes'    => $attributes,
        ];
    }

    public function show(string $slug, Request $request, ViewTrackingService $tracker)
    {
        $asset = Asset::where('slug', $slug)
            ->where(fn($q) => $q->published()
                ->orWhere('status', AssetStatus::SoldOut)
                // A listing held at "Bid Accepted" is still public — it has to
                // be, so the winning bidder can come back and pay.
                ->orWhere('status', AssetStatus::BidAccepted))
            ->with(['seller','category.attributes','coverImage','images','attributeValues.attribute','acceptedBid.bidder'])
            ->withCount('favorites')
            ->firstOrFail();

        $tracker->record($asset, $request);

        // Related: same subcategory, same price range ±50%, exclude self, limit 6
        $priceMin = (int)($asset->price * 0.5);
        $priceMax = (int)($asset->price * 1.5);
        $related  = Asset::published()
            ->where('category_id', $asset->category_id)
            ->where('id', '!=', $asset->id)
            ->whereBetween('price', [$priceMin, $priceMax])
            ->with(['coverImage', 'seller', 'category'])
            ->limit(6)->get();

        $isFavorited = auth()->check()
            ? auth()->user()->favorites()->where('asset_id', $asset->id)->exists()
            : false;

        $viewer  = auth()->user();
        $isOwner = $viewer !== null && (int) $viewer->id === (int) $asset->user_id;
        $type    = $asset->inventoryType();

        // Bidding exists only on single-item listings. Every flag below is
        // computed here, on the server, from the policy and the listing state —
        // the client is told what it may do, it never decides.
        $biddable   = $type->allowsBidding();
        $topBid     = $biddable ? $asset->topBid() : null;
        $bidCount   = $biddable ? $asset->bids()->count() : 0;
        $canBid     = $viewer !== null && Gate::forUser($viewer)->allows('create', [Bid::class, $asset]);
        $recentBids = $biddable
            ? $asset->bids()->with('bidder')->orderByDesc('id')->limit(10)->get()
                ->map(fn (Bid $bid) => [
                    'id'               => $bid->id,
                    'amount_formatted' => Money::format((int) $bid->amount),
                    'bidder_name'      => self::bidderLabel($bid, $viewer, $isOwner),
                    'bidder_initial'   => mb_strtoupper(mb_substr($bid->bidder?->name ?? '?', 0, 1)),
                    'status'           => $bid->status->value,
                    'status_label'     => $bid->status->label(),
                    'placed_human'     => $bid->created_at?->diffForHumans(null, true),
                    'is_mine'          => $viewer !== null && (int) $bid->bidder_user_id === (int) $viewer->id,
                    'is_top'           => $topBid !== null && (int) $topBid->id === (int) $bid->id,
                    'can_accept'       => $viewer !== null && Gate::forUser($viewer)->allows('accept', $bid),
                    'can_reject'       => $viewer !== null && Gate::forUser($viewer)->allows('reject', $bid),
                    'can_cancel'       => $viewer !== null && Gate::forUser($viewer)->allows('cancel', $bid),
                ])->values()->all()
            : [];

        $acceptedBid = $asset->acceptedBid;
        $wonByViewer = $acceptedBid !== null
            && $viewer !== null
            && (int) $acceptedBid->bidder_user_id === (int) $viewer->id;

        // Build structured data (safe values only from DB)
        $structuredDataJson = json_encode([
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $asset->title,
            'description' => Str::limit(strip_tags($asset->description ?? ''), 300),
            'url'         => route('marketplace.show', $asset->slug),
            'offers'      => [
                '@type'         => 'Offer',
                'priceCurrency' => 'BDT',
                'price'         => number_format($asset->price / 100, 2, '.', ''),
                // A listing whose bid was accepted is off the market even though
                // it is not sold yet, so it must not advertise itself as in stock.
                'availability'  => $asset->isSoldOut() || $asset->hasAcceptedBid()
                    ? 'https://schema.org/OutOfStock'
                    : 'https://schema.org/InStock',
                'url'           => route('marketplace.show', $asset->slug),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ogImage = $asset->coverImage?->url();
        $description = Str::limit(strip_tags($asset->description ?? ''), 155);

        return Inertia::render('Marketplace/Show', [
            'asset' => [
                'id'                 => $asset->id,
                'slug'               => $asset->slug,
                'title'              => $asset->title,
                'description'        => $asset->description,
                'price_formatted'    => Money::format((int) $asset->price),
                'quantity'           => (int) $asset->quantity,
                'available_quantity' => (int) $asset->available_quantity,
                'is_sold_out'        => $asset->isSoldOut(),
                'is_featured'        => $asset->isFeaturedNow(),
                'is_purchasable'     => $asset->isAvailableForPurchase(),
                'status'             => $asset->status->value,
                'status_label'       => $asset->status->label(),
                // How this listing sells: one unique item, a counted stock, or
                // unlimited copies. Only the first of the three accepts bids.
                'inventory_type'     => $type->value,
                'inventory_label'    => $type->label(),
                'is_unlimited'       => $asset->isUnlimited(),
                'allows_bidding'     => $asset->allowsBidding(),
                'has_accepted_bid'   => $asset->hasAcceptedBid(),
                'top_bid_formatted'  => $topBid ? Money::format((int) $topBid->amount) : null,
                'bid_count'          => $bidCount,
                // The only floor a new bid has to clear: one poisha over the
                // current top bid, or any positive amount when there is none.
                'min_bid_bdt'        => number_format(
                    Money::toBdt($topBid ? (int) $topBid->amount + 1 : 1),
                    2, '.', ''
                ),
                'views_count'        => (int) $asset->views_count,
                'favorites_count'    => (int) $asset->favorites_count,
                'listed_on'          => $asset->created_at?->format('d M Y'),
                'images'             => $asset->images->map(fn ($image) => ['url' => $image->url()])->values()->all(),
                'category'           => [
                    'name'   => $asset->category?->name,
                    'slug'   => $asset->category?->slug,
                    'icon'   => $asset->category?->icon,
                    'parent' => $asset->category?->parent ? [
                        'name' => $asset->category->parent->name,
                        'slug' => $asset->category->parent->slug,
                    ] : null,
                ],
                'seller'             => [
                    'name'               => $asset->seller?->name,
                    'initial'            => strtoupper(mb_substr($asset->seller?->name ?? '?', 0, 1)),
                    'is_verified_seller' => (bool) $asset->seller?->isVerifiedSeller(),
                    'member_since'       => $asset->seller?->created_at?->format('M Y'),
                    'bio'                => $asset->seller?->bio ? Str::limit($asset->seller->bio, 100) : null,
                    'profile_url'        => $asset->seller
                        ? route('profile.show', $asset->seller->username ?? $asset->seller->id)
                        : null,
                ],
                // URLs resolved server-side so the client never needs the owner's id.
                'checkout_url'       => route('checkout.show', $asset->slug),
                'bid_url'            => route('bids.store', $asset->slug),
                'contact_url'        => route('listings.contact', $asset->slug),
                'attributes'         => $asset->attributeValues->map(fn ($value) => [
                    'label' => $value->attribute?->label,
                    'value' => $value->value,
                    'unit'  => $value->attribute?->unit,
                ])->filter(fn ($row) => $row['label'] !== null)->values()->all(),
            ],
            'related'      => $related->map(self::mapAsset())->values()->all(),
            'isFavorited'  => $isFavorited,
            // Whether the viewer owns this listing — decided on the server, never
            // inferred client-side from an id comparison.
            'canManage'    => auth()->check() && auth()->id() === $asset->user_id,
            'manageUrl'    => auth()->check() && auth()->id() === $asset->user_id
                ? route('dashboard.listings.show', $asset)
                : null,
            'canBid'       => $canBid,
            'canContact'   => $viewer !== null && !$isOwner && $viewer->canTransact(),
            'bids'         => $recentBids,
            // Shown to both sides once a bid is accepted: who won, for how much,
            // and — for the winner only — where to pay.
            'acceptedBid'  => $acceptedBid ? [
                'id'               => $acceptedBid->id,
                'amount_formatted' => Money::format((int) $acceptedBid->amount),
                'buyer_name'       => $acceptedBid->bidder?->name ?? 'Unknown',
                'is_mine'          => $wonByViewer,
                'pay_url'          => $wonByViewer
                    ? route('checkout.show', ['slug' => $asset->slug, 'bid' => $acceptedBid->id])
                    : null,
            ] : null,
            'seo'          => [
                'description' => $description,
                'canonical'   => route('marketplace.show', $asset->slug),
                'ogImage'     => $ogImage,
                'jsonLd'      => $structuredDataJson,
            ],
        ]);
    }

    /**
     * How a bid's owner is named in the public history.
     *
     * The listing's own seller sees full names — they are deciding whose bid to
     * accept. Everyone else sees "Rahim A.", because a public bid list is not a
     * reason to publish someone's full name next to what they can afford.
     */
    private static function bidderLabel(Bid $bid, ?User $viewer, bool $isOwner): string
    {
        if ($viewer !== null && (int) $bid->bidder_user_id === (int) $viewer->id) {
            return 'You';
        }

        $name = trim($bid->bidder?->name ?? '');

        if ($name === '') {
            return 'Bidder';
        }

        if ($isOwner) {
            return $name;
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = $parts[0] ?? '';

        if (count($parts) < 2) {
            return $first !== '' ? $first : 'Bidder';
        }

        return $first . ' ' . mb_strtoupper(mb_substr((string) end($parts), 0, 1)) . '.';
    }
}
