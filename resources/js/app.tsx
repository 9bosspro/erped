import '../css/app.css';
import './echo';

import DynamicLayout from '@/layouts/dynamic-layout';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// หน้า main app — ใช้ inline layout ของตัวเอง ไม่ inject DynamicLayout
const mainPages = import.meta.glob<{ default: React.ComponentType }>(
    './pages/**/*.tsx',
);

// หน้าจากทุก module ใน engine/modules/*/resources/js/pages/
const modulePages = import.meta.glob<{ default: React.ComponentType }>(
    '../../engine/modules/*/resources/js/pages/**/*.tsx',
);

/**
 * แปลง component name → glob key พร้อม inject DynamicLayout สำหรับ module pages
 *
 * - module pages  → inject DynamicLayout เป็น default (ถ้า page ไม่ได้ override .layout เอง)
 * - main pages    → คืนตรงๆ ไม่แตะ (มี inline layout อยู่แล้ว เช่น dashboard.tsx)
 */
async function resolvePage(name: string) {
    const parts = name.split('/');

    if (parts.length >= 2) {
        const first = parts[0];
        const ModuleName = first.charAt(0).toUpperCase() + first.slice(1);
        const pagePath = parts.slice(1).join('/');
        const moduleKey = `../../engine/modules/${ModuleName}/resources/js/pages/${pagePath}.tsx`;

        if (moduleKey in modulePages) {
            const page = await resolvePageComponent(moduleKey, modulePages);
            // inject DynamicLayout เฉพาะ module pages ที่ไม่ได้ประกาศ .layout เอง
            (page as { default: { layout?: unknown } }).default.layout ??=
                (child: React.ReactNode) => <DynamicLayout>{child}</DynamicLayout>;
            return page;
        }
    }

    // main app pages — ไม่ inject, ใช้ inline layout ที่มีอยู่แล้ว
    return resolvePageComponent(`./pages/${name}.tsx`, mainPages);
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: resolvePage,
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
