import { Head, Link } from '@inertiajs/react';

const NAV = [
    { label: '1. Backend (sidebar)', href: '/layout-demo/backend',    active: false },
    { label: '2. Frontend (navbar)', href: '/layout-demo/frontend',   active: false },
    { label: '3. Auth (card)',       href: '/layout-demo/auth',       active: false },
    { label: '4. Fullscreen',        href: '/layout-demo/fullscreen', active: true  },
    { label: '5. Bare (no layout)',  href: '/layout-demo/bare',       active: false },
];

/**
 * ประกาศ .layout เอง → fullscreen hero layout (ไม่มี navbar/sidebar)
 * เหมาะกับ landing page, splash screen, onboarding flow
 */
export default function LayoutDemoFullscreen() {
    return (
        <>
            <Head title="Demo — Fullscreen Layout" />

            <div className="flex flex-col items-center justify-center gap-8 text-center">
                <div>
                    <span className="inline-block rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300 px-3 py-0.5 text-xs font-medium mb-4">
                        Fullscreen Layout
                    </span>
                    <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-3">
                        เต็มหน้าจอ ไม่มี wrapper
                    </h1>
                    <p className="text-gray-500 dark:text-gray-400 max-w-md">
                        ประกาศ <code className="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded text-sm">.layout</code> บน page component
                        เพื่อ override DynamicLayout เป็น fullscreen container
                        เหมาะกับ landing page, hero section, onboarding
                    </p>
                </div>

                <pre className="rounded-lg bg-gray-900 text-gray-100 p-4 text-xs text-left max-w-lg w-full overflow-x-auto">
                    <code>{`LayoutDemoFullscreen.layout = (page) => (
    <div className="min-h-screen bg-gradient-to-br
        from-orange-50 to-amber-50
        dark:from-gray-950 dark:to-gray-900
        flex items-center justify-center">
        {page}
    </div>
);`}
                    </code>
                </pre>

                <div className="flex flex-wrap justify-center gap-2">
                    {NAV.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`rounded-lg border px-3 py-1.5 text-sm transition-colors ${
                                item.active
                                    ? 'border-orange-500 bg-orange-50 text-orange-700 dark:border-orange-400 dark:bg-orange-950 dark:text-orange-300'
                                    : 'border-gray-200 hover:border-gray-400 text-gray-600 dark:border-gray-700 dark:text-gray-400'
                            }`}
                        >
                            {item.label}
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

LayoutDemoFullscreen.layout = (page: React.ReactNode) => (
    <div className="min-h-screen bg-gradient-to-br from-orange-50 to-amber-50 dark:from-gray-950 dark:to-gray-900 flex items-center justify-center p-8">
        {page}
    </div>
);
