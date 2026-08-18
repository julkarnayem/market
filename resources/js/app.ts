import '../css/app.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { route, ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Nayem Store';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Ziggy's config used to arrive twice per page load: inlined by @routes in
        // the Blade root and again in the shared `ziggy` prop. @routes is gone, so
        // the prop is the only source — but @routes also inlined route.umd.js,
        // which is what defined the global route() that every <script setup> calls
        // without an import. Install that global from the bundle instead.
        //
        // `location` is dropped on purpose: it is shared for SSR only (ssr.ts has
        // no window). Baking the boot-time URL into the browser config would
        // freeze route().current() at the first page and break active-nav
        // highlighting on every later visit; omitting it makes Ziggy read the
        // live window.location, which is what @routes' config did.
        const { location: _ssrOnly, ...ziggy } = props.initialPage.props.ziggy;
        globalThis.Ziggy = ziggy;
        globalThis.route = route;

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, ziggy)
            .mount(el);
    },
    progress: {
        color: '#10B981',
    },
});
