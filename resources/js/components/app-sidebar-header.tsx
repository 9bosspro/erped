import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { logout } from '@/routes';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { Link, router } from '@inertiajs/react';
import { LogOut } from 'lucide-react';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const handleLogout = () => {
        router.flushAll();
    };

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <Button variant="ghost" size="sm" asChild>
                <Link href={logout()} as="button" onClick={handleLogout}>
                    <LogOut className="h-4 w-4" />
                    <span className="ml-2 hidden sm:inline">Log out</span>
                </Link>
            </Button>
        </header>
    );
}
