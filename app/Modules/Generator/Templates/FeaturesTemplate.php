<?php

declare(strict_types=1);

namespace App\Modules\Generator\Templates;

/**
 * Szablon bazowy dla komponentu Features.
 */
final class FeaturesTemplate
{
    /**
     * Zwróć kod komponentu Features.
     *
     * @param  array<string, mixed>  $variables  Zmienne
     */
    public static function getCode(array $variables = []): string
    {
        $title = $variables['texts']['title'] ?? 'Nasze funkcje';
        $subtitle = $variables['texts']['subtitle'] ?? 'Odkryj co oferujemy';
        $features = $variables['texts']['features'] ?? [
            ['title' => 'Szybkość', 'description' => 'Błyskawiczne działanie'],
            ['title' => 'Bezpieczeństwo', 'description' => 'Najwyższe standardy'],
            ['title' => 'Wsparcie', 'description' => 'Zawsze pomożemy'],
        ];
        $primaryColor = $variables['colors']['primary'] ?? '#3b82f6';

        $featuresHtml = '';
        foreach ($features as $feature) {
            $featuresHtml .= <<<TSX
            <div className="p-6 bg-white dark:bg-gray-800 rounded-lg shadow-lg">
              <h3 className="text-xl font-semibold mb-2" style={{ color: '{$primaryColor}' }}>
                {$feature['title']}
              </h3>
              <p className="text-gray-600 dark:text-gray-300">
                {$feature['description']}
              </p>
            </div>
TSX;
        }

        return <<<TSX
'use client';

export default function Features() {
  return (
    <section className="py-20 bg-white dark:bg-gray-900">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-16">
          <h2 className="text-3xl sm:text-4xl md:text-5xl font-bold mb-4">
            {$title}
          </h2>
          <p className="text-xl text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
            {$subtitle}
          </p>
        </div>
        
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {$featuresHtml}
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
            'colors' => [
                'primary' => '#3b82f6',
            ],
            'texts' => [
                'title' => 'Nasze funkcje',
                'subtitle' => 'Odkryj co oferujemy',
                'features' => [
                    ['title' => 'Szybkość', 'description' => 'Błyskawiczne działanie'],
                    ['title' => 'Bezpieczeństwo', 'description' => 'Najwyższe standardy'],
                    ['title' => 'Wsparcie', 'description' => 'Zawsze pomożemy'],
                ],
            ],
        ];
    }
}
