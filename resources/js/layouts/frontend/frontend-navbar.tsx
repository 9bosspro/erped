import { Link, usePage } from '@inertiajs/react';

export default function FrontendNavbar() {
    const { auth } = usePage<{ auth?: { user?: { name: string } } }>().props;

    return (
        <header className="sticky top-0 z-50 border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
            <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
                <Link href="/" className="text-lg font-semibold text-gray-900 dark:text-white">
                    {import.meta.env.VITE_APP_NAME ?? 'App'}
                </Link>

                <nav className="flex items-center gap-6 text-sm">
                    {/* TODO: เพิ่ม nav links ตามต้องการ */}

                    {auth?.user ? (
                        <Link
                            href="/dashboard"
                            className="rounded-md bg-gray-900 px-3 py-1.5 text-white dark:bg-white dark:text-gray-900"
                        >
                            Dashboard
                        </Link>
                    ) : (
                        <>
                            <Link href="/login" className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                                เข้าสู่ระบบ
                            </Link>
                            <Link
                                href="/register"
                                className="rounded-md bg-gray-900 px-3 py-1.5 text-white dark:bg-white dark:text-gray-900"
                            >
                                สมัครสมาชิก
                            </Link>
                        </>
                    )}
                </nav>
            </div>
        </header>
    );
}
