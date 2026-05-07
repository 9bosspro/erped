<?php

declare(strict_types=1);

namespace Engine\Modules\Demo\Http\Controllers;

use App\Http\Controllers\BaseInertiaController;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Core\Base\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Inertia\Response as InertiaResponse;

class LabController extends BaseInertiaController
{
    use ApiResponseTrait;

    protected string $pagePrefix = 'demo';

    public function __construct(
        private readonly \Engine\Modules\Demo\Services\KeyLabService $keyLabService,
        private readonly SodiumHelper $sodium,
        private readonly JwtHelper $jwtHelper,
    ) {}

    /**
     * แสดงหน้า Lab ผ่าน Inertia
     */
    public function index(): InertiaResponse
    {
        return $this->inertia('lab/index', [
            'message' => 'Demo Module — Inertia integration สำเร็จ',
            'items'   => ['item-1', 'item-2', 'item-3'],
        ]);
    }

    /**
     * JSON endpoint: ทดสอบ key generation ผ่าน master
     */
    public function lab1(): JsonResponse
    {
        $masterClient = app('slave.master');
        if ($masterClient->ping() === false) {
            return $this->sendError('Master is not ping available');
        }

        try {
            $keys = $this->keyLabService->generateKeys();
            return $this->sendResponse($keys, 'Key generated successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
