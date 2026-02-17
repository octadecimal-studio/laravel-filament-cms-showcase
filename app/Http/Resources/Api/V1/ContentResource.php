<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Content\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource dla kontentu.
 *
 * @mixin SiteContent
 */
final class ContentResource extends JsonResource
{
    /**
     * Transformuje zasób do tablicy.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Pobierz dane z opublikowanej wersji
        $publication = $this->publications->first();
        $versionData = $publication?->version?->data ?? $this->data ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'data' => $versionData,
            'meta' => $this->meta,
            'order' => $this->order,
            'version' => [
                'number' => $publication?->version?->version ?? $this->version,
                'published_at' => $publication?->published_at?->toIso8601String(),
            ],
            'children' => ContentResource::collection($this->whenLoaded('children')),
        ];
    }
}
