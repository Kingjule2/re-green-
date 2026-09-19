<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'role_short_label' => $this->role->shortLabel(),
            'organization' => $this->organization,
            'is_farmer' => $this->isFarmer(),
            'views_all_lands' => $this->viewsAllLands(),
            'initials' => $this->initials(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
