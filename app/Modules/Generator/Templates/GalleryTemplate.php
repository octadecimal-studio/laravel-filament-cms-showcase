<?php

declare(strict_types=1);

namespace App\Modules\Generator\Templates;

/**
 * Szablon bazowy dla komponentu Gallery.
 */
final class GalleryTemplate
{
    /**
     * Zwróć kod komponentu Gallery.
     *
     * @param  array<string, mixed>  $variables  Zmienne
     */
    public static function getCode(array $variables = []): string
    {
        $title = $variables['texts']['title'] ?? 'Galeria';
        $subtitle = $variables['texts']['subtitle'] ?? 'Zobacz nasze prace';
        $images = $variables['texts']['images'] ?? [
            'https://via.placeholder.com/400x300',
            'https://via.placeholder.com/400x300',
            'https://via.placeholder.com/400x300',
        ];

        $imagesHtml = '';
        foreach ($images as $image) {
            $imagesHtml .= <<<TSX
            <div className="overflow-hidden rounded-lg shadow-lg">
              <img
                src="{$image}"
                alt="Gallery image"
                className="w-full h-full object-cover hover:scale-110 transition-transform duration-300"
              />
            </div>
TSX;
        }

        return <<<TSX
'use client';

export default function Gallery() {
  return (
    <section className="py-20 bg-gray-50 dark:bg-gray-800">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-16">
          <h2 className="text-3xl sm:text-4xl md:text-5xl font-bold mb-4">
            {$title}
          </h2>
          <p className="text-xl text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
            {$subtitle}
          </p>
        </div>
        
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {$imagesHtml}
        </div>
      </div>
    </section>
  );
}
TSX;
    }

    /**
     * Zwróć domyślne zmienne.
     *
     * @return array<string, mixed>
     */
    public static function getDefaultVariables(): array
    {
        return [
            'texts' => [
                'title' => 'Galeria',
                'subtitle' => 'Zobacz nasze prace',
                'images' => [
                    'https://via.placeholder.com/400x300',
                    'https://via.placeholder.com/400x300',
                    'https://via.placeholder.com/400x300',
                ],
            ],
        ];
    }
}
