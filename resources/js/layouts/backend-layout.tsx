import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

interface BackendLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

/**
 * Layout หลักสำหรับ backend/admin area
 * ใช้ AppLayout (sidebar) เป็นฐาน
 * modules ที่เป็น backend ให้ตั้ง $layout = 'backend' ใน controller
 */
export default function BackendLayout({ children, breadcrumbs, ...props }: BackendLayoutProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs} {...props}>
            {children}
        </AppLayout>
    );
}
