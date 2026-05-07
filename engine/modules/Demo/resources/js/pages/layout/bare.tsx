import { Head, Link } from '@inertiajs/react';

const NAV = [
    { label: '1. Backend (sidebar)', href: '/layout-demo/backend',    active: false },
    { label: '2. Frontend (navbar)', href: '/layout-demo/frontend',   active: false },
    { label: '3. Auth (card)',       href: '/layout-demo/auth',       active: false },
    { label: '4. Fullscreen',        href: '/layout-demo/fullscreen', active: false },
    { label: '5. Bare (no layout)',  href: '/layout-demo/bare',       active: true  },
];

/**
 * .layout = fragment เปล่า → ไม่มี wrapper เลย
 * เหมาะกับ print page, iframe embed, PDF export view
 */
export default function LayoutDemoBare() {
    return (
        <>
            <Head title="Demo — Bare (No Layout)" />

            <div
                style={{ fontFamily: 'sans-serif', padding: '2rem', maxWidth: '640px', margin: '0 auto' }}
            >
                <div
                    style={{
                        display: 'inline-block',
                        background: '#f3f4f6',
                        color: '#374151',
                        borderRadius: '999px',
                        padding: '2px 12px',
                        fontSize: '12px',
                        fontWeight: 600,
                        marginBottom: '16px',
                    }}
                >
                    Bare / No Layout
                </div>

                <h1 style={{ fontSize: '1.5rem', fontWeight: 700, marginBottom: '8px' }}>
                    ไม่มี layout wrapper เลย
                </h1>
                <p style={{ color: '#6b7280', marginBottom: '16px' }}>
                    ตั้ง <code style={{ background: '#f3f4f6', padding: '1px 6px', borderRadius: '4px' }}>.layout</code> เป็น fragment เปล่า
                    — เหมาะกับ print view, PDF export, iframe embed, หรือ custom shell ของตัวเอง
                </p>

                <pre
                    style={{
                        background: '#111827',
                        color: '#f9fafb',
                        padding: '1rem',
                        borderRadius: '8px',
                        fontSize: '12px',
                        overflowX: 'auto',
                        marginBottom: '24px',
                    }}
                >
                    <code>{`// .layout = fragment → ไม่มี wrapper เลย
LayoutDemoBare.layout = (page) => <>{page}</>;`}</code>
                </pre>

                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
                    {NAV.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            style={{
                                border: item.active ? '1px solid #374151' : '1px solid #d1d5db',
                                background: item.active ? '#111827' : 'transparent',
                                color: item.active ? '#f9fafb' : '#6b7280',
                                borderRadius: '8px',
                                padding: '6px 12px',
                                fontSize: '14px',
                                textDecoration: 'none',
                            }}
                        >
                            {item.label}
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

// ไม่มี wrapper เลย — React fragment เปล่า
LayoutDemoBare.layout = (page: React.ReactNode) => <>{page}</>;
