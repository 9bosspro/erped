<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Resources\AuthUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * ผูก version กับ hash ของ Vite manifest — เมื่อ deploy ใหม่ Inertia
     * จะตรวจ version mismatch แล้วบังคับ full reload เพื่อโหลด asset ล่าสุด
     * ป้องกัน user ค้างใน asset เก่าจน hit 404
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        $manifestPath = public_path('build/manifest.json');

        if (is_file($manifestPath)) {
            return (string) filemtime($manifestPath) . ':' . md5_file($manifestPath);
        }

        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            // ── App identity ──────────────────────────────────────
            'name'   => config('app.name'),
            'locale' => app()->getLocale(),

            // ── Auth (DTO เพื่อกัน leak field อ่อนไหวจาก User model) ──
            'auth' => [
                'user' => fn () => $request->user()
                    ? (new AuthUserResource($request->user()))->toArray($request)
                    : null,
            ],

            // ── CSRF token สำหรับ form / fetch ภายนอก Inertia ─────
            'csrf_token' => fn (): string => csrf_token() ?? '',

            // ── Flash messages — read-once จาก session ────────────
            'flash' => fn (): array => [
                'success' => Session::get('success'),
                'error'   => Session::get('error'),
                'warning' => Session::get('warning'),
                'info'    => Session::get('info'),
            ],

            // ── UI state ──────────────────────────────────────────
            'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
