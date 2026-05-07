import { Head, usePage } from '@inertiajs/react';

interface Props {
    message: string;
    items: string[];
}

export default function LabIndex() {
    const { message, items } = usePage<Props>().props;

    return (
        <>
            <Head title="Demo Lab dfsdfs" />

            <div className="min-h-screen bg-gray-50 p-8 dark:bg-gray-900">
                <div className="mx-auto max-w-2xl">
                    <h1 className="mb-2 text-2xl font-semibold text-gray-900 dark:text-white">
                        Demo Module — Labcdfdddfsdf ok
                    </h1>

                    <p className="mb-6 text-gray-600 dark:text-gray-400">
                        {message}
                    </p>

                    {items.length > 0 && (
                        <ul className="space-y-2">
                            {items.map((item) => (
                                <li
                                    key={item}
                                    className="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300"
                                >
                                    {item}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}
