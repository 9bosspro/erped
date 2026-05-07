import { Head, Link } from '@inertiajs/react';

const NAV = [
    { label: '1. Backend (sidebar)', href: '/layout-demo/backend',    active: true  },
    { label: '2. Frontend (navbar)', href: '/layout-demo/frontend',   active: false },
    { label: '3. Auth (card)',       href: '/layout-demo/auth',       active: false },
    { label: '4. Fullscreen',        href: '/layout-demo/fullscreen', active: false },
    { label: '5. Bare (no layout)',  href: '/layout-demo/bare',       active: false },
];

/**
 * ไม่มี .layout → DynamicLayout อ่าน _layout='backend' → BackendLayout (sidebar)
 */
export default function LayoutDemoBackend() {
    return (
        <>
            <Head title="Demo — Backend Layout" />

            <div className="p-6 space-y-6">
                <Badge color="blue">Backend Layout</Badge>

                <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-white mb-1">
                        Backend Layout (Sidebar)
                    </h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        ใช้เมื่อ controller ตั้ง <code className="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">$layout = 'backend'</code> (ค่า default)
                        <br />
                        DynamicLayout อ่าน <code className="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">_layout</code> prop แล้วเลือก BackendLayout (AppSidebarLayout)
                    </p>

                    <CodeBlock>{`class MyController extends BaseInertiaController
{
    protected string $pagePrefix = 'my-module';
    // $layout = 'backend' คือค่า default
}`}</CodeBlock>
                </div>

                <DemoNav items={NAV} />
            </div>
        </>
    );
}

/* ───────────── shared demo helpers ───────────── */

function Badge({ children, color }: { children: React.ReactNode; color: 'blue' | 'green' | 'purple' | 'orange' | 'gray' }) {
    const colors = {
        blue:   'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        green:  'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        purple: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
        orange: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
        gray:   'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    };
    return (
        <span className={`inline-block rounded-full px-3 py-0.5 text-xs font-medium ${colors[color]}`}>
            {children}
        </span>
    );
}

function CodeBlock({ children }: { children: React.ReactNode }) {
    return (
        <pre className="rounded-lg bg-gray-900 text-gray-100 p-4 text-xs overflow-x-auto">
            <code>{children}</code>
        </pre>
    );
}

function DemoNav({ items }: { items: typeof NAV }) {
    return (
        <div>
            <p className="text-xs text-gray-400 mb-2 uppercase tracking-wide">ทดลอง layout อื่น</p>
            <div className="flex flex-wrap gap-2">
                {items.map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={`rounded-lg border px-3 py-1.5 text-sm transition-colors ${
                            item.active
                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-blue-400 dark:bg-blue-950 dark:text-blue-300'
                                : 'border-gray-200 hover:border-gray-400 text-gray-600 dark:border-gray-700 dark:text-gray-400 dark:hover:border-gray-500'
                        }`}
                    >
                        {item.label}
                    </Link>
                ))}
            </div>
        </div>
    );
}
