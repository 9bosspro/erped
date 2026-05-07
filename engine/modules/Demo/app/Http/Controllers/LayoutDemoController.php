<?php

declare(strict_types=1);

namespace Engine\Modules\Demo\Http\Controllers;

use App\Http\Controllers\BaseInertiaController;
use Inertia\Response as InertiaResponse;

/**
 * LayoutDemoController — แสดง layout และ media demos ทุกรูปแบบ
 *
 * Layout demos:
 *   GET /layout-demo/backend    → backend (sidebar)
 *   GET /layout-demo/frontend   → frontend (navbar + footer)
 *   GET /layout-demo/auth       → auth card
 *   GET /layout-demo/fullscreen → fullscreen hero
 *   GET /layout-demo/bare       → ไม่มี layout
 *
 * Media demos:
 *   GET /layout-demo/gallery → image gallery + lightbox
 *   GET /layout-demo/youtube → YouTube embed playlist
 *   GET /layout-demo/music   → HTML5 audio player
 */
class LayoutDemoController extends BaseInertiaController
{
    protected string $pagePrefix = 'demo';

    public function backend(): InertiaResponse
    {
        return $this->inertia('layout/backend', ['_layout' => 'backend']);
    }

    public function frontend(): InertiaResponse
    {
        return $this->inertia('layout/frontend', ['_layout' => 'frontend']);
    }

    public function auth(): InertiaResponse
    {
        return $this->inertia('layout/auth');
    }

    public function fullscreen(): InertiaResponse
    {
        return $this->inertia('layout/fullscreen');
    }

    public function bare(): InertiaResponse
    {
        return $this->inertia('layout/bare');
    }

    public function gallery(): InertiaResponse
    {
        return $this->inertia('media/gallery', ['_layout' => 'frontend']);
    }

    public function youtube(): InertiaResponse
    {
        return $this->inertia('media/youtube', ['_layout' => 'frontend']);
    }

    public function music(): InertiaResponse
    {
        return $this->inertia('media/music', ['_layout' => 'frontend']);
    }
}
