import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const mainPages = import.meta.glob<{ default: React.ComponentType }>(
    './pages/**/*.tsx',
);

const modulePages = import.meta.glob<{ default: React.ComponentType }>(
    '../../engine/modules/*/resources/js/pages/**/*.tsx',
);

function resolvePage(name: string) {
    const parts = name.split('/');

    if (parts.length >= 2) {
        const first = parts[0];
        const ModuleName = first.charAt(0).toUpperCase() + first.slice(1);
        const pagePath = parts.slice(1).join('/');
        const moduleKey = `../../engine/modules/${ModuleName}/resources/js/pages/${pagePath}.tsx`;

        if (moduleKey in modulePages) {
            return resolvePageComponent(moduleKey, modulePages);
        }
    }

    return resolvePageComponent(`./pages/${name}.tsx`, mainPages);
}

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: resolvePage,
        setup: ({ App, props }) => {
            return <App {...props} />;
        },
    }),
);
