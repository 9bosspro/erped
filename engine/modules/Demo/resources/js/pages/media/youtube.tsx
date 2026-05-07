import { Head } from '@inertiajs/react';
import { Play } from 'lucide-react';
import { useState } from 'react';

const PLAYLIST = [
    {
        id:       'jfKfPfyJRdk',
        title:    'Lofi Hip Hop Radio',
        channel:  'Lofi Girl',
        duration: 'Live',
        desc:     'เพลงเบาๆ สำหรับการทำงานหรือเรียน',
    },
    {
        id:       'rUxyKA_-grg',
        title:    'Nature Soundscape — Forest',
        channel:  'Relaxing Sounds',
        duration: '3:00:00',
        desc:     'เสียงธรรมชาติจากป่า เพิ่มสมาธิในการทำงาน',
    },
    {
        id:       'n61ULEU7CO0',
        title:    'Lofi Beats to Chill',
        channel:  'Chillhop Music',
        duration: 'Live',
        desc:     'บรรยากาศผ่อนคลาย เหมาะกับช่วงพักกลางวัน',
    },
] as const;

const MEDIA_NAV = [
    { label: 'Gallery',      href: '/layout-demo/gallery', active: false },
    { label: 'YouTube',      href: '/layout-demo/youtube', active: true  },
    { label: 'Music Player', href: '/layout-demo/music',   active: false },
];

export default function YoutubeDemo() {
    const [activeId, setActiveId] = useState(PLAYLIST[0].id);

    const current = PLAYLIST.find(v => v.id === activeId) ?? PLAYLIST[0];

    return (
        <>
            <Head title="Demo — YouTube Embed" />

            <div className="max-w-5xl mx-auto px-4 py-8 space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">YouTube Embed</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        ใช้ <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">youtube-nocookie.com</code> เพื่อ privacy — ไม่ tracking cookies จนกว่าจะกด play
                    </p>
                </div>

                <div className="grid lg:grid-cols-3 gap-6">
                    {/* Player */}
                    <div className="lg:col-span-2 space-y-3">
                        <div className="relative w-full aspect-video rounded-xl overflow-hidden bg-black shadow-xl">
                            <iframe
                                key={activeId}
                                src={`https://www.youtube-nocookie.com/embed/${activeId}?rel=0&modestbranding=1`}
                                title={current.title}
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowFullScreen
                                className="absolute inset-0 w-full h-full"
                            />
                        </div>
                        <div>
                            <h2 className="font-semibold text-gray-900 dark:text-white">{current.title}</h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400">{current.channel}</p>
                            <p className="text-sm text-gray-600 dark:text-gray-300 mt-1">{current.desc}</p>
                        </div>
                    </div>

                    {/* Playlist */}
                    <div className="space-y-2">
                        <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Playlist</h3>
                        {PLAYLIST.map((video, i) => (
                            <button
                                key={video.id}
                                onClick={() => setActiveId(video.id)}
                                className={`w-full text-left flex gap-3 p-3 rounded-xl transition-colors ${
                                    video.id === activeId
                                        ? 'bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-800/60 border border-transparent'
                                }`}
                            >
                                <div className="relative flex-shrink-0 w-20 aspect-video rounded-lg overflow-hidden bg-gray-200 dark:bg-gray-700">
                                    <img
                                        src={`https://img.youtube.com/vi/${video.id}/mqdefault.jpg`}
                                        alt={video.title}
                                        className="w-full h-full object-cover"
                                    />
                                    {video.id === activeId && (
                                        <div className="absolute inset-0 bg-red-600/70 flex items-center justify-center">
                                            <Play className="size-4 text-white fill-white" />
                                        </div>
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className={`text-xs font-medium leading-snug line-clamp-2 ${
                                        video.id === activeId ? 'text-red-700 dark:text-red-400' : 'text-gray-800 dark:text-gray-200'
                                    }`}>
                                        {i + 1}. {video.title}
                                    </p>
                                    <p className="text-xs text-gray-400 mt-0.5">{video.duration}</p>
                                </div>
                            </button>
                        ))}
                    </div>
                </div>

                <MediaNav items={MEDIA_NAV} />
            </div>
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
                                ? 'border-red-500 bg-red-50 text-red-700 dark:border-red-400 dark:bg-red-950 dark:text-red-300'
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
