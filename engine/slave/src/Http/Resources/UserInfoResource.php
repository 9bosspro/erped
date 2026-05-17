<?php

declare(strict_types=1);

namespace Slave\Http\Resources;

use App\Models\User;
use Core\Base\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class UserInfoResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'email' => $resource->email,
            'created_at' => $resource->created_at,
            'updated_at' => $resource->updated_at,
        ];
    }
}
