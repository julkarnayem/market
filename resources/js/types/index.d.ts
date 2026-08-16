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

/** Props shared with every Inertia response (see HandleInertiaRequests::share). */
export interface SharedProps {
    appName: string;
    auth: {
        user: User | null;
    };
    flash: Flash;
    ziggy: ZiggyConfig & { location: string };
    [key: string]: unknown;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> =
    T & SharedProps;

declare global {
    // Ziggy's route() helper, available globally after ZiggyVue is installed.
    // eslint-disable-next-line no-var
    var route: typeof ziggyRoute;
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
