import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h, type DefineComponent } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { route, ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Nayem Store';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} — ${appName}` : appName),
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
            ),
        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) });
            app.use(plugin);
            // Ziggy for SSR: config is shared from the server via HandleInertiaRequests.
            // There is no window.location here, so pass the request URL explicitly —
            // Ziggy v2 wants location as {host, pathname, search}, not a string.
            const url = new URL(page.props.ziggy.location);
            const ziggy = {
                ...page.props.ziggy,
                location: {
                    host: url.host,
                    pathname: url.pathname,
                    search: url.search,
                },
            };
            // ZiggyVue only reaches template scope, but every <script setup> calls
            // bare route(), which in the browser resolves to the global app.ts
            // installs. Mirror it here or those calls throw during renderToString.
            // Note for whoever turns SSR on: this is a per-request global in a
            // long-lived process, so `location` is only reliable for the render
            // that set it — concurrent renders can interleave at await points.
            globalThis.Ziggy = ziggy;
            globalThis.route = route;
            app.use(ZiggyVue, ziggy);
            return app;
        },
    }),
);
