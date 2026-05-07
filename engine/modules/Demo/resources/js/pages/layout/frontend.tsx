import { Head, Link } from '@inertiajs/react';

const NAV = [
    { label: '1. Backend (sidebar)', href: '/layout-demo/backend',    active: false },
    { label: '2. Frontend (navbar)', href: '/layout-demo/frontend',   active: true  },
    { label: '3. Auth (card)',       href: '/layout-demo/auth',       active: false },
    { label: '4. Fullscreen',        href: '/layout-demo/fullscreen', active: false },
    { label: '5. Bare (no layout)',  href: '/layout-demo/bare',       active: false },
];

/**
 * ไม่มี .layout → DynamicLayout อ่าน _layout='frontend' → FrontendLayout (navbar+footer)
 */
export default function LayoutDemoFrontend() {
    return (
        <>
            <Head title="Demo — Frontend Layout" />

            <div className="space-y-6">
                <Badge color="green">Frontend Layout</Badge>

                <div className="rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-white mb-1">
                        Frontend Layout (Navbar + Footer)
                    </h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        ใช้เมื่อ controller ตั้ง <code className="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">$layout = 'frontend'</code>
                        <br />
                        เหมาะกับ public-facing modules เช่น User, Blog, Shop
                    </p>

                    <CodeBlock>{`class UserController extends BaseInertiaController
{
    protected string $pagePrefix = 'user';
    protected string $layout     = 'frontend';
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
                                ? 'border-green-500 bg-green-50 text-green-700 dark:border-green-400 dark:bg-green-950 dark:text-green-300'
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
