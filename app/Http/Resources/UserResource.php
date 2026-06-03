<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserResource extends InertiaJsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'position' => $this->positionSummary(),
        ];
    }

    /**
     * @return array{id: int, title: string, code: string, department: string|null}|null
     */
    private function positionSummary(): ?array
    {
        if (! $this->relationLoaded('position') || ! $this->position) {
            return null;
        }

        return [
            'id' => (int) $this->position->id,
            'title' => $this->position->title,
            'code' => $this->position->code,
            'department' => $this->position->relationLoaded('department')
                ? $this->position->department?->name
                : null,
        ];
    }
}
