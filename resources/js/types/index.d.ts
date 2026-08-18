import type { Config as ZiggyConfig } from 'ziggy-js';
import { route as ziggyRoute } from 'ziggy-js';

/** The authenticated user, as shared by app/Http/Middleware/HandleInertiaRequests.php.
 *  Kept intentionally lean — Laravel remains authoritative; these booleans only drive
 *  what the UI *shows*, never what it is *allowed* to do. */
export interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    phone: string | null;
    avatar: string | null;
    is_admin: boolean;
    can_sell: boolean;
    can_transact: boolean;
    is_verified_seller: boolean;
    verification_status: string | null;
    status: string | null;
    roles: string[];
    permissions: string[];
}

export interface Flash {
    success: string | null;
    error: string | null;
    status: string | null;
}

/** A category as mapped by MapsMarketplaceProps::mapCategory(). */
export interface Category {
    slug: string;
    name: string;
    icon: string | null;
    children_count: number;
}

/** One entry from Laravel's paginator `links` array. */
export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/** Laravel's LengthAwarePaginator, as serialised into an Inertia prop. */
export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

/** A filterable EAV attribute, from MarketplaceController::index(). */
export interface DynamicAttribute {
    /** Request key, already prefixed — e.g. "attr_12". */
    key: string;
    label: string;
    type: 'text' | 'number' | 'decimal' | 'boolean' | 'select' | 'multiselect' | 'date' | 'url';
    unit: string | null;
    options: string[];
}

/** Marketplace filter state, echoed back by the server so the form follows the URL. */
export interface MarketplaceFilters {
    q: string | null;
    category: string | null;
    subcategory: string | null;
    min_price: string | null;
    max_price: string | null;
    verified_only: boolean;
    featured_only: boolean;
    in_stock: boolean;
    sort: string;
    attributes: Record<string, string>;
}

/** An asset card's payload, as mapped by MapsMarketplaceProps::mapAsset().
 *  Deliberately a whitelist — the Asset model has many more columns, and the
 *  client only needs what a card renders. Prices arrive pre-formatted because
 *  they are stored as integer poisha and App\Support\Money owns the
 *  formatting; never re-derive currency on the client. */
export interface AssetCardData {
    id: number;
    slug: string;
    title: string;
    price_formatted: string;
    quantity: number;
    available_quantity: number;
    is_sold_out: boolean;
    is_featured: boolean;
    is_favorited: boolean;
    cover_image_url: string | null;
    category: {
        name: string;
        icon: string | null;
    };
    seller: {
        name: string;
        is_verified_seller: boolean;
        profile_url: string;
    };
}

/** Props shared with every Inertia response (see HandleInertiaRequests::share). */
export interface SharedProps {
    appName: string;
    auth: {
        user: User | null;
    };
    flash: Flash;
    /** HandleInertiaRequests shares `location` as an absolute URL string, whereas
     *  Ziggy's own Config types it as {host, pathname, search} — so override it
     *  rather than intersect, which would produce an impossible type. */
    ziggy: Omit<ZiggyConfig, 'location'> & { location: string };
    [key: string]: unknown;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> =
    T & SharedProps;

declare global {
    // Ziggy's route() helper. The @routes directive used to define this by
    // inlining route.umd.js; app.ts (and ssr.ts) now assign it from the bundle.
    // eslint-disable-next-line no-var
    var route: typeof ziggyRoute;
    // The route table a bare route() call reads when given no explicit config.
    // Sourced from the shared `ziggy` prop, minus `location` in the browser.
    // eslint-disable-next-line no-var
    var Ziggy: ZiggyConfig;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof ziggyRoute;
    }
}

declare module '@inertiajs/core' {
    interface PageProps extends SharedProps {}
}

export {};
