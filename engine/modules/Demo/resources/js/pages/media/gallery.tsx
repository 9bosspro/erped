import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, X, ZoomIn } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

const IMAGES = [
    { id: 1,  seed: 'arch1',    caption: 'Urban Architecture',  category: 'City'    },
    { id: 2,  seed: 'nature2',  caption: 'Forest Path',         category: 'Nature'  },
    { id: 3,  seed: 'sea3',     caption: 'Ocean Sunset',        category: 'Nature'  },
    { id: 4,  seed: 'city4',    caption: 'City Lights',         category: 'City'    },
    { id: 5,  seed: 'mount5',   caption: 'Mountain Peak',       category: 'Nature'  },
    { id: 6,  seed: 'street6',  caption: 'Street Photography',  category: 'City'    },
    { id: 7,  seed: 'lake7',    caption: 'Calm Lake',           category: 'Nature'  },
    { id: 8,  seed: 'build8',   caption: 'Modern Building',     category: 'City'    },
    { id: 9,  seed: 'forest9',  caption: 'Autumn Forest',       category: 'Nature'  },
    { id: 10, seed: 'bridge10', caption: 'Old Bridge',          category: 'City'    },
    { id: 11, seed: 'river11',  caption: 'River Valley',        category: 'Nature'  },
    { id: 12, seed: 'night12',  caption: 'Night Skyline',       category: 'City'    },
] as const;

const MEDIA_NAV = [
    { label: 'Gallery',       href: '/layout-demo/gallery', active: true  },
    { label: 'YouTube',       href: '/layout-demo/youtube', active: false },
    { label: 'Music Player',  href: '/layout-demo/music',   active: false },
];

export default function GalleryDemo() {
    const [lightbox, setLightbox] = useState<number | null>(null);
    const [filter,   setFilter]   = useState<'All' | 'Nature' | 'City'>('All');

    const filtered = IMAGES.filter(img => filter === 'All' || img.category === filter);

    const prev = useCallback(() => {
        setLightbox(i => (i === null ? null : (i - 1 + filtered.length) % filtered.length));
    }, [filtered.length]);

    const next = useCallback(() => {
        setLightbox(i => (i === null ? null : (i + 1) % filtered.length));
    }, [filtered.length]);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (lightbox === null) return;
            if (e.key === 'ArrowLeft')  prev();
            if (e.key === 'ArrowRight') next();
            if (e.key === 'Escape')     setLightbox(null);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [lightbox, prev, next]);

    useEffect(() => {
        document.body.style.overflow = lightbox !== null ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    }, [lightbox]);

    const current = lightbox !== null ? filtered[lightbox] : null;

    return (
        <>
            <Head title="Demo — Image Gallery" />

            <div className="max-w-6xl mx-auto px-4 py-8 space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Image Gallery</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Grid + Lightbox — คลิกรูปเพื่อขยาย / ←→ เปลี่ยนรูป / ESC ปิด
                    </p>
                </div>

                {/* Filter tabs */}
                <div className="flex gap-2">
                    {(['All', 'Nature', 'City'] as const).map(cat => (
                        <button
                            key={cat}
                            onClick={() => { setFilter(cat); setLightbox(null); }}
                            className={`rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
                                filter === cat
                                    ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700'
                            }`}
                        >
                            {cat}
                        </button>
                    ))}
                    <span className="ml-auto text-sm text-gray-400 self-center">{filtered.length} photos</span>
                </div>

                {/* Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    {filtered.map((img, index) => (
                        <button
                            key={img.id}
                            onClick={() => setLightbox(index)}
                            className="group relative aspect-[4/3] overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <img
                                src={`https://picsum.photos/seed/${img.seed}/400/300`}
                                alt={img.caption}
                                loading="lazy"
                                className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-110"
                            />
                            <div className="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-colors duration-300 flex items-center justify-center">
                                <ZoomIn className="text-white opacity-0 group-hover:opacity-100 transition-opacity size-8 drop-shadow" />
                            </div>
                            <div className="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/60 to-transparent p-2 translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                                <p className="text-white text-xs font-medium truncate">{img.caption}</p>
                            </div>
                        </button>
                    ))}
                </div>

                <MediaNav items={MEDIA_NAV} />
            </div>

            {/* Lightbox */}
            {current && (
                <div
                    className="fixed inset-0 z-50 bg-black/95 flex items-center justify-center"
                    onClick={() => setLightbox(null)}
                >
                    {/* Close */}
                    <button
                        onClick={() => setLightbox(null)}
                        className="absolute top-4 right-4 text-white/70 hover:text-white transition-colors"
                        aria-label="Close"
                    >
                        <X className="size-7" />
                    </button>

                    {/* Counter */}
                    <span className="absolute top-4 left-4 text-white/60 text-sm">
                        {(lightbox ?? 0) + 1} / {filtered.length}
                    </span>

                    {/* Prev */}
                    <button
                        onClick={(e) => { e.stopPropagation(); prev(); }}
                        className="absolute left-4 text-white/70 hover:text-white transition-colors p-2"
                        aria-label="Previous"
                    >
                        <ChevronLeft className="size-9" />
                    </button>

                    {/* Image */}
                    <div className="px-16 max-w-5xl w-full" onClick={e => e.stopPropagation()}>
                        <img
                            src={`https://picsum.photos/seed/${current.seed}/1200/800`}
                            alt={current.caption}
                            className="max-h-[80vh] w-full object-contain rounded-lg shadow-2xl"
                        />
                        <p className="text-center text-white/80 mt-3 text-sm">{current.caption}</p>
                    </div>

                    {/* Next */}
                    <button
                        onClick={(e) => { e.stopPropagation(); next(); }}
                        className="absolute right-4 text-white/70 hover:text-white transition-colors p-2"
                        aria-label="Next"
                    >
                        <ChevronRight className="size-9" />
                    </button>
                </div>
            )}
        </>
    );
}

function MediaNav({ items }: { items: typeof MEDIA_NAV }) {
    return (
        <div>
            <p className="text-xs text-gray-400 mb-2 uppercase tracking-wide">Media demos อื่น</p>
            <div className="flex flex-wrap gap-2">
                {items.map(item => (
                    <a
                        key={item.href}
                        href={item.href}
                        className={`rounded-lg border px-3 py-1.5 text-sm transition-colors ${
                            item.active
                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-blue-400 dark:bg-blue-950 dark:text-blue-300'
                                : 'border-gray-200 hover:border-gray-400 text-gray-600 dark:border-gray-700 dark:text-gray-400 dark:hover:border-gray-500'
                        }`}
                    >
                        {item.label}
                    </a>
                ))}
            </div>
        </div>
    );
}
