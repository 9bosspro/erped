export default function FrontendFooter() {
    const year = new Date().getFullYear();

    return (
        <footer className="border-t border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
                <p className="text-center text-sm text-gray-500 dark:text-gray-400">
                    © {year} {import.meta.env.VITE_APP_NAME ?? 'App'}. All rights reserved.
                </p>
            </div>
        </footer>
    );
}
