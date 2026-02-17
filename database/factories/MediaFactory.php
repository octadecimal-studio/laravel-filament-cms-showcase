<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Content\Models\Media;
use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Factory dla Media.
 *
 * @extends Factory<Media>
 */
final class MediaFactory extends Factory
{
    /**
     * Model dla factory.
     */
    protected $model = Media::class;

    /**
     * Definicja domyślnych atrybutów.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $file = UploadedFile::fake()->image('test-image.jpg', 1920, 1080);
        $path = Storage::disk('public')->putFile('media', $file);

        return [
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'disk' => 'public',
            'width' => 1920,
            'height' => 1080,
            'alt_text' => fake()->sentence(),
            'caption' => fake()->paragraph(),
            'metadata' => [
                'exif' => [],
            ],
            'variants' => [
                'thumbnail' => [
                    'path' => $path,
                    'width' => 150,
                    'height' => 150,
                ],
                'medium' => [
                    'path' => $path,
                    'width' => 800,
                    'height' => 600,
                ],
            ],
            'collection' => 'gallery',
            'tags' => fake()->words(3),
            'is_public' => false,
            'is_active' => true,
        ];
    }

    /**
     * State: Przypisz do tenanta.
     *
     * @return Factory<Media>
     */
    public function forTenant(Tenant $tenant): Factory
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * State: Obraz.
     *
     * @return Factory<Media>
     */
    public function image(): Factory
    {
        return $this->state(function (array $attributes) {
            $file = UploadedFile::fake()->image('image.jpg', 1920, 1080);
            $path = Storage::disk('public')->putFile('media', $file);

            return [
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'width' => 1920,
                'height' => 1080,
            ];
        });
    }

    /**
     * State: Dokument.
     *
     * @return Factory<Media>
     */
    public function document(): Factory
    {
        return $this->state(function (array $attributes) {
            $file = UploadedFile::fake()->create('document.pdf', 1024);
            $path = Storage::disk('public')->putFile('media', $file);

            return [
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'size' => $file->getSize(),
                'width' => null,
                'height' => null,
            ];
        });
    }

    /**
     * State: Publiczny.
     *
     * @return Factory<Media>
     */
    public function public(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }
}
