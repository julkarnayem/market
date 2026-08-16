<?php
namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Models\Asset;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Services\ViewTrackingService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MarketplaceController extends Controller
{
    private const ALLOWED_SORTS = [
        'newest', 'oldest', 'price_asc', 'price_desc', 'popular', 'featured',
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

        return view('marketplace.index', compact(
            'assets', 'rootCategories', 'currentCategory', 'currentSubcategory',
            'dynamicAttributes', 'sort', 'userFavoriteIds'
        ));
    }

    public function show(string $slug, Request $request, ViewTrackingService $tracker)
    {
        $asset = Asset::where('slug', $slug)
            ->where(fn($q) => $q->published()->orWhere('status', AssetStatus::SoldOut))
            ->with(['seller','category.attributes','coverImage','images','attributeValues.attribute'])
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

        $userActiveOffer = auth()->check()
            ? $asset->offers()->where('buyer_user_id', auth()->id())->where('status', 'pending')->where('expires_at', '>', now())->first()
            : null;

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
                'availability'  => $asset->isSoldOut()
                    ? 'https://schema.org/OutOfStock'
                    : 'https://schema.org/InStock',
                'url'           => route('marketplace.show', $asset->slug),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $structuredData = '<script type="application/ld+json">'.PHP_EOL.$structuredDataJson.PHP_EOL.'</script>';
        $ogImage = $asset->coverImage?->url();
        $description = Str::limit(strip_tags($asset->description ?? ''), 155);

        return view('marketplace.show', compact('asset', 'related', 'isFavorited', 'userActiveOffer'));
    }
}
