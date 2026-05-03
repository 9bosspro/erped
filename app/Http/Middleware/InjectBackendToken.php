<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\BackendApi\TokenManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * InjectBackendToken — เพิ่ม Backend access token เข้า request attributes
 *
 * ให้ middleware และ controllers ถัดไปเข้าถึง token ผ่าน request attributes
 * แทนที่จะเรียก session() โดยตรง — ลด coupling กับ session key names
 */
class InjectBackendToken
{
    public function __construct(
        private readonly TokenManager $tokenManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('backend_token', $this->tokenManager->getToken());

        return $next($request);
    }
}
