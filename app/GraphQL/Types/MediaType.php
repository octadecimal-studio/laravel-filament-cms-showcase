<?php

declare(strict_types=1);

namespace App\GraphQL\Types;

use App\Modules\Content\Models\Media;

/**
 * GraphQL Type resolver dla Media.
 *
 * Dodaje custom resolvers dla `url` i `variantUrl`.
 */
class MediaType
{
    /**
     * Resolver dla pola `url` w Media.
     */
    public function url(Media $media): string
    {
        return $media->getUrl();
    }

    /**
     * Resolver dla pola `variantUrl` w Media.
     *
     * @param  array<string, mixed>  $args
     */
    public function variantUrl(Media $media, array $args): ?string
    {
        $variant = $args['variant'] ?? 'thumbnail';

        return $media->getVariantUrl($variant);
    }
}
