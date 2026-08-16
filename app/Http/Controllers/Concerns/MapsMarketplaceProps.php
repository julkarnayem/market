<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Asset;
use App\Models\Category;
use App\Support\Money;

/**
 * Shared Blade -> Inertia prop mapping for anything that renders an asset or
 * category card. Kept as controller concerns (rather than API Resources) so the
 * whitelist stays visible next to the queries that feed it.
 *
 * Both mappers are explicit whitelists, mirroring the discipline in
 * HandleInertiaRequests::shareUser: the client receives only what a card
 * renders, so extra columns cannot leak and payloads stay small.
 *
 * @see resources/js/types/index.d.ts for the matching TypeScript shapes.
 */
trait MapsMarketplaceProps
{
    /** Category -> CategoryCard / filter-list props. */
    protected static function mapCategory(): callable
    {
        return fn (Category $category): array => [
            'slug'           => $category->slug,
            'name'           => $category->name,
            'icon'           => $category->icon,
            'children_count' => (int) ($category->children_count ?? 0),
        ];
    }

    /**
     * Asset -> AssetCard props.
     *
     * Prices are formatted here because amounts are stored as integer poisha and
     * App\Support\Money owns that formatting — never re-derive currency client-side.
     *
     * @param  array<int,mixed>  $favoritedIds  Asset ids keyed by id (isset lookup).
     */
    protected static function mapAsset(array $favoritedIds = []): callable
    {
        return fn (Asset $asset): array => [
            'id'                 => $asset->id,
            'slug'               => $asset->slug,
            'title'              => $asset->title,
            'price_formatted'    => Money::format((int) $asset->price),
            'quantity'           => (int) $asset->quantity,
            'available_quantity' => (int) $asset->available_quantity,
            'is_sold_out'        => $asset->isSoldOut(),
            'is_featured'        => $asset->isFeaturedNow(),
            'is_favorited'       => isset($favoritedIds[$asset->id]),
            'cover_image_url'    => $asset->coverImage?->url(),
            'category'           => [
                'name' => $asset->category?->name ?? 'Uncategorised',
                'icon' => $asset->category?->icon,
            ],
            'seller'             => [
                'name'               => $asset->seller?->name ?? 'Unknown seller',
                'is_verified_seller' => (bool) $asset->seller?->isVerifiedSeller(),
                'profile_url'        => $asset->seller
                    ? route('profile.show', $asset->seller->username ?? $asset->seller->id)
                    : route('marketplace.index'),
            ],
        ];
    }
}
