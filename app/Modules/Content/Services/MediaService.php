<?php

declare(strict_types=1);

namespace App\Modules\Content\Services;

use App\Modules\Content\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Service do zarządzania mediami.
 *
 * Obsługuje upload, resize, optimize obrazów używając Intervention Image 3.
 */
final class MediaService
{
    /**
     * Image Manager (Intervention Image 3).
     */
    private ImageManager $imageManager;

    /**
     * Domyślne rozmiary wariantów.
     *
     * @var array<string, array{width: int, height: int, quality: int}>
     */
    private array $variants = [
        'thumbnail' => ['width' => 150, 'height' => 150, 'quality' => 80],
        'small' => ['width' => 400, 'height' => 300, 'quality' => 85],
        'medium' => ['width' => 800, 'height' => 600, 'quality' => 85],
        'large' => ['width' => 1920, 'height' => 1080, 'quality' => 90],
    ];

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver);
    }

    /**
     * Upload pliku i utwórz rekord Media.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upload(UploadedFile $file, array $attributes = []): Media
    {
        $disk = $attributes['disk'] ?? 'public';
        $collection = $attributes['collection'] ?? 'gallery';
        $tenantId = $attributes['tenant_id'] ?? null;

        if (! $tenantId) {
            throw new \InvalidArgumentException('tenant_id is required');
        }

        // Upload oryginalnego pliku
        $path = $file->store("media/{$collection}", $disk);

        if ($path === false) {
            throw new \RuntimeException('Failed to store file');
        }

        $fullPath = Storage::disk($disk)->path($path);

        // Dla obrazów: generuj warianty i wyciągnij metadane
        $variants = [];
        $width = null;
        $height = null;
        $metadata = null;

        $mimeType = $file->getMimeType();
        if ($mimeType !== null && str_starts_with($mimeType, 'image/')) {
            $image = $this->imageManager->read($fullPath);
            $width = $image->width();
            $height = $image->height();

            // Generuj warianty
            $variants = $this->generateVariants($image, $path, $disk, $collection);

            // Wyciągnij metadane (EXIF)
            $metadata = $this->extractMetadata($image);
        }

        // Utwórz rekord Media
        /** @var Media $media */
        $media = Media::create([
            'tenant_id' => $tenantId,
            'project_id' => $attributes['project_id'] ?? null,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'disk' => $disk,
            'width' => $width,
            'height' => $height,
            'alt_text' => $attributes['alt_text'] ?? null,
            'caption' => $attributes['caption'] ?? null,
            'metadata' => $metadata,
            'variants' => $variants,
            'collection' => $collection,
            'tags' => $attributes['tags'] ?? null,
            'is_public' => $attributes['is_public'] ?? false,
            'is_active' => true,
        ]);

        return $media;
    }

    /**
     * Generuj warianty obrazu (thumbnail, small, medium, large).
     *
     * @return array<string, array{path: string, width: int, height: int}>
     */
    private function generateVariants(\Intervention\Image\Interfaces\ImageInterface $image, string $originalPath, string $disk, string $collection): array
    {
        $variants = [];
        $pathInfo = pathinfo($originalPath);
        $directory = $pathInfo['dirname'] ?? '';
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? 'jpg';

        foreach ($this->variants as $name => $config) {
            $variantImage = clone $image;

            // Resize z zachowaniem proporcji
            $variantImage->scale(
                width: $config['width'],
                height: $config['height']
            );

            // Zapisz wariant
            $variantPath = "{$directory}/{$filename}_{$name}.{$extension}";
            $variantFullPath = Storage::disk($disk)->path($variantPath);

            $variantImage->save($variantFullPath, quality: $config['quality']);

            $variants[$name] = [
                'path' => $variantPath,
                'width' => $variantImage->width(),
                'height' => $variantImage->height(),
            ];
        }

        return $variants;
    }

    /**
     * Wyciągnij metadane z obrazu (EXIF).
     *
     * @return array<string, mixed>|null
     */
    private function extractMetadata(\Intervention\Image\Interfaces\ImageInterface $image): ?array
    {
        try {
            $exif = $image->exif();

            return [
                'exif' => $exif ?: [],
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Usuń media i wszystkie warianty.
     */
    public function delete(Media $media): bool
    {
        $disk = $media->disk;

        // Usuń oryginalny plik
        if (Storage::disk($disk)->exists($media->file_path)) {
            Storage::disk($disk)->delete($media->file_path);
        }

        // Usuń warianty
        $variants = $media->variants ?? [];
        foreach ($variants as $variant) {
            $variantPath = $variant['path'] ?? null;
            if ($variantPath && Storage::disk($disk)->exists($variantPath)) {
                Storage::disk($disk)->delete($variantPath);
            }
        }

        // Usuń rekord
        $media->delete();

        return true;
    }

    /**
     * Zoptymalizuj obraz (kompresja, strip metadata).
     */
    public function optimize(Media $media): bool
    {
        if (! $media->isImage()) {
            return false;
        }

        $fullPath = $media->getFullPath();

        if (! file_exists($fullPath)) {
            return false;
        }

        try {
            $image = $this->imageManager->read($fullPath);

            // Strip metadata (opcjonalnie)
            // $image->strip();

            // Zapisz zoptymalizowany
            $image->save($fullPath, quality: 85);

            // Zaktualizuj rozmiar
            $media->update([
                'size' => filesize($fullPath),
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
