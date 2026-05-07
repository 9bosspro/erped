import BackendLayout from '@/layouts/backend-layout';
import FrontendLayout from '@/layouts/frontend-layout';
import { usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';

type LayoutArea = 'backend' | 'frontend';

const LAYOUTS: Record<LayoutArea, React.ComponentType<{ children: ReactNode }>> = {
    backend:  BackendLayout,
    frontend: FrontendLayout,
};

/**
 * Layout selector — อ่าน _layout จาก Inertia page props แล้วเลือก layout ให้อัตโนมัติ
 *
 * controller ตั้ง $layout = 'backend'|'frontend'
 * BaseInertiaController แนบ _layout ไปกับทุก render
 * ทำงานเป็น persistent layout — ไม่ re-mount เมื่อ navigate ภายใน area เดียวกัน
 */
export default function DynamicLayout({ children }: { children: ReactNode }) {
    const { _layout } = usePage<{ _layout?: LayoutArea }>().props;

    const Layout = LAYOUTS[_layout ?? 'backend'];

    return <Layout>{children}</Layout>;
}
