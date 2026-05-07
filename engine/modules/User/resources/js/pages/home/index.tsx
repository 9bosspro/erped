import { Head, usePage } from '@inertiajs/react';

interface Props {
    message: string;
}

export default function HomeIndex() {
    const { message } = usePage<Props>().props;

    return (
        <>
            <Head title="หน้าแรก" />

            <div className="py-12 text-center">
                <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
                    {message}
                </h1>
                <p className="mt-4 text-gray-500 dark:text-gray-400">
                    หน้านี้ใช้ FrontendLayout — Navbar + Footer โดยอัตโนมัติ
                </p>
            </div>
        </>
    );
}
