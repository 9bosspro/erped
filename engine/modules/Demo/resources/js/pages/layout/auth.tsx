import AuthSimpleLayout from '@/layouts/auth/auth-simple-layout';
import { Head, Link } from '@inertiajs/react';

const NAV = [
    { label: '1. Backend (sidebar)', href: '/layout-demo/backend',    active: false },
    { label: '2. Frontend (navbar)', href: '/layout-demo/frontend',   active: false },
    { label: '3. Auth (card)',       href: '/layout-demo/auth',       active: true  },
    { label: '4. Fullscreen',        href: '/layout-demo/fullscreen', active: false },
    { label: '5. Bare (no layout)',  href: '/layout-demo/bare',       active: false },
];

/**
 * ประกาศ .layout เอง → override DynamicLayout → ใช้ AuthSimpleLayout (card กลางจอ)
 * เหมาะกับหน้า login / register / forgot-password ใน module
 */
export default function LayoutDemoAuth() {
    return (
        <>
            <Head title="Demo — Auth Layout" />

            <div className="space-y-5">
                <p className="text-sm text-gray-500 dark:text-gray-400 text-center">
                    หน้านี้ใช้ <strong>AuthSimpleLayout</strong> ผ่าน page-level <code className="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">.layout</code> override
                </p>

                <pre className="rounded-lg bg-gray-900 text-gray-100 p-4 text-xs overflow-x-auto">
                    <code>{`// ประกาศ .layout บน component — override DynamicLayout
LayoutDemoAuth.layout = (page) => (
    <AuthSimpleLayout title="..." description="...">
        {page}
    </AuthSimpleLayout>
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
                                    ? 'border-purple-500 bg-purple-50 text-purple-700 dark:border-purple-400 dark:bg-purple-950 dark:text-purple-300'
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

LayoutDemoAuth.layout = (page: React.ReactNode) => (
    <AuthSimpleLayout title="Auth Layout Demo" description="ตัวอย่าง page-level .layout override">
        {page}
    </AuthSimpleLayout>
);
