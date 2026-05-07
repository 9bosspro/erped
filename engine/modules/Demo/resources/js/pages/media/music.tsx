import { Head } from '@inertiajs/react';
import {
    Pause,
    Play,
    SkipBack,
    SkipForward,
    Volume2,
    VolumeX,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

const TRACKS = [
    {
        id:     1,
        title:  'SoundHelix Song 1',
        artist: 'T. Schürger',
        genre:  'Electronic',
        color:  'from-violet-500 to-purple-700',
        src:    'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
    },
    {
        id:     2,
        title:  'SoundHelix Song 2',
        artist: 'T. Schürger',
        genre:  'Ambient',
        color:  'from-blue-500 to-cyan-700',
        src:    'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
    },
    {
        id:     3,
        title:  'SoundHelix Song 3',
        artist: 'T. Schürger',
        genre:  'Jazz',
        color:  'from-orange-500 to-rose-700',
        src:    'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3',
    },
] as const;

const MEDIA_NAV = [
    { label: 'Gallery',      href: '/layout-demo/gallery', active: false },
    { label: 'YouTube',      href: '/layout-demo/youtube', active: false },
    { label: 'Music Player', href: '/layout-demo/music',   active: true  },
];

function fmt(sec: number): string {
    if (!isFinite(sec)) return '0:00';
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
}

export default function MusicDemo() {
    const audioRef                              = useRef<HTMLAudioElement>(null);
    const [trackIdx, setTrackIdx]               = useState(0);
    const [isPlaying, setIsPlaying]             = useState(false);
    const [currentTime, setCurrentTime]         = useState(0);
    const [duration, setDuration]               = useState(0);
    const [volume, setVolume]                   = useState(0.8);
    const [muted, setMuted]                     = useState(false);

    const track = TRACKS[trackIdx];

    // sync audio src เมื่อเปลี่ยน track
    useEffect(() => {
        const audio = audioRef.current;
        if (!audio) return;
        audio.src    = track.src;
        audio.volume = volume;
        audio.muted  = muted;
        setCurrentTime(0);
        setDuration(0);
        if (isPlaying) {
            audio.play().catch(() => setIsPlaying(false));
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [trackIdx]);

    useEffect(() => {
        const audio = audioRef.current;
        if (!audio) return;
        audio.volume = volume;
    }, [volume]);

    useEffect(() => {
        const audio = audioRef.current;
        if (!audio) return;
        audio.muted = muted;
    }, [muted]);

    const togglePlay = useCallback(() => {
        const audio = audioRef.current;
        if (!audio) return;
        if (isPlaying) {
            audio.pause();
            setIsPlaying(false);
        } else {
            audio.play().then(() => setIsPlaying(true)).catch(() => setIsPlaying(false));
        }
    }, [isPlaying]);

    const skipTo = useCallback((idx: number) => {
        setTrackIdx(idx);
        setIsPlaying(true);
    }, []);

    const prev = useCallback(() => skipTo((trackIdx - 1 + TRACKS.length) % TRACKS.length), [trackIdx, skipTo]);
    const next = useCallback(() => skipTo((trackIdx + 1) % TRACKS.length), [trackIdx, skipTo]);

    const seek = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        const audio = audioRef.current;
        if (!audio) return;
        const val = Number(e.target.value);
        audio.currentTime = val;
        setCurrentTime(val);
    }, []);

    const progress = duration > 0 ? (currentTime / duration) * 100 : 0;

    return (
        <>
            <Head title="Demo — Music Player" />

            {/* hidden audio element */}
            <audio
                ref={audioRef}
                src={track.src}
                onTimeUpdate={e => setCurrentTime(e.currentTarget.currentTime)}
                onDurationChange={e => setDuration(e.currentTarget.duration)}
                onEnded={next}
            />

            <div className="max-w-4xl mx-auto px-4 py-8 space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Music Player</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        HTML5 Audio API — play/pause, seek, volume, playlist
                    </p>
                </div>

                <div className="grid md:grid-cols-5 gap-6">
                    {/* Player card */}
                    <div className="md:col-span-3 rounded-2xl overflow-hidden shadow-xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                        {/* Album art */}
                        <div className={`h-52 bg-gradient-to-br ${track.color} flex items-center justify-center`}>
                            <div className={`size-28 rounded-full bg-white/20 flex items-center justify-center shadow-inner transition-all duration-300 ${isPlaying ? 'animate-spin [animation-duration:8s]' : ''}`}>
                                <div className="size-10 rounded-full bg-white/40" />
                            </div>
                        </div>

                        <div className="p-6 space-y-4">
                            {/* Track info */}
                            <div className="text-center">
                                <p className="font-semibold text-gray-900 dark:text-white text-lg leading-tight">{track.title}</p>
                                <p className="text-sm text-gray-500 dark:text-gray-400">{track.artist} · <span className="text-xs">{track.genre}</span></p>
                            </div>

                            {/* Seek bar */}
                            <div className="space-y-1">
                                <input
                                    type="range"
                                    min={0}
                                    max={duration || 0}
                                    step={0.1}
                                    value={currentTime}
                                    onChange={seek}
                                    className="w-full h-1.5 rounded-full accent-violet-600 cursor-pointer"
                                    style={{ background: `linear-gradient(to right, #7c3aed ${progress}%, #e5e7eb ${progress}%)` }}
                                />
                                <div className="flex justify-between text-xs text-gray-400">
                                    <span>{fmt(currentTime)}</span>
                                    <span>{fmt(duration)}</span>
                                </div>
                            </div>

                            {/* Controls */}
                            <div className="flex items-center justify-center gap-6">
                                <button onClick={prev} className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                                    <SkipBack className="size-6 fill-current" />
                                </button>
                                <button
                                    onClick={togglePlay}
                                    className={`size-14 rounded-full flex items-center justify-center shadow-lg transition-all active:scale-95 bg-gradient-to-br ${track.color} hover:opacity-90`}
                                >
                                    {isPlaying
                                        ? <Pause className="size-6 text-white fill-white" />
                                        : <Play  className="size-6 text-white fill-white ml-0.5" />
                                    }
                                </button>
                                <button onClick={next} className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                                    <SkipForward className="size-6 fill-current" />
                                </button>
                            </div>

                            {/* Volume */}
                            <div className="flex items-center gap-2">
                                <button onClick={() => setMuted(m => !m)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                    {muted ? <VolumeX className="size-4" /> : <Volume2 className="size-4" />}
                                </button>
                                <input
                                    type="range"
                                    min={0}
                                    max={1}
                                    step={0.01}
                                    value={muted ? 0 : volume}
                                    onChange={e => { setVolume(Number(e.target.value)); setMuted(false); }}
                                    className="flex-1 h-1 rounded-full accent-violet-600 cursor-pointer"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Playlist */}
                    <div className="md:col-span-2 space-y-2">
                        <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Playlist</h3>
                        {TRACKS.map((t, i) => (
                            <button
                                key={t.id}
                                onClick={() => skipTo(i)}
                                className={`w-full text-left flex items-center gap-3 p-3 rounded-xl border transition-colors ${
                                    i === trackIdx
                                        ? 'border-violet-300 bg-violet-50 dark:border-violet-700 dark:bg-violet-950/40'
                                        : 'border-transparent hover:bg-gray-50 dark:hover:bg-gray-800/60'
                                }`}
                            >
                                <div className={`size-10 rounded-lg bg-gradient-to-br ${t.color} flex items-center justify-center flex-shrink-0`}>
                                    {i === trackIdx && isPlaying
                                        ? <Pause className="size-4 text-white fill-white" />
                                        : <Play  className="size-4 text-white fill-white ml-0.5" />
                                    }
                                </div>
                                <div className="min-w-0">
                                    <p className={`text-sm font-medium truncate ${i === trackIdx ? 'text-violet-700 dark:text-violet-300' : 'text-gray-800 dark:text-gray-200'}`}>
                                        {t.title}
                                    </p>
                                    <p className="text-xs text-gray-400 truncate">{t.artist}</p>
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
                                ? 'border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-400 dark:bg-violet-950 dark:text-violet-300'
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
