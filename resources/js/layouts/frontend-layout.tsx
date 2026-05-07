import FrontendFooter from '@/layouts/frontend/frontend-footer';
import FrontendNavbar from '@/layouts/frontend/frontend-navbar';
import { type ReactNode } from 'react';

interface FrontendLayoutProps {
    children: ReactNode;
}

/**
 * Layout หลักสำหรับ frontend/user area
 * modules ที่เป็น frontend ให้ตั้ง $layout = 'frontend' ใน controller
 */
export default function FrontendLayout({ children }: FrontendLayoutProps) {
    return (
        <div className="flex min-h-screen flex-col bg-white dark:bg-gray-950">
            <FrontendNavbar />

            <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6">
                {children}
            </main>

            <FrontendFooter />
        </div>
    );
}
