<?php

declare(strict_types=1);

namespace App\Modules\Generator\Templates;

/**
 * Szablon bazowy dla komponentu Hero.
 */
final class HeroTemplate
{
    /**
     * Zwróć kod komponentu Hero.
     *
     * @param  array<string, mixed>  $variables  Zmienne
     */
    public static function getCode(array $variables = []): string
    {
        $title = $variables['texts']['title'] ?? 'Witaj w naszym serwisie';
        $subtitle = $variables['texts']['subtitle'] ?? 'Tworzymy nowoczesne rozwiązania';
        $ctaText = $variables['texts']['cta'] ?? 'Rozpocznij';
        $primaryColor = $variables['colors']['primary'] ?? '#3b82f6';
        $fontFamily = $variables['fonts']['heading'] ?? 'Inter';

        return <<<TSX
'use client';

import { motion } from 'framer-motion';

export default function Hero() {
  return (
    <section className="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center">
          <motion.h1
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold mb-6"
            style={{ fontFamily: '{$fontFamily}', color: '{$primaryColor}' }}
          >
            {$title}
          </motion.h1>
          
          <motion.p
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.2 }}
            className="text-xl sm:text-2xl text-gray-600 dark:text-gray-300 mb-8 max-w-2xl mx-auto"
          >
            {$subtitle}
          </motion.p>
          
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            className="flex flex-col sm:flex-row gap-4 justify-center"
          >
            <button
              className="px-8 py-4 bg-{$primaryColor} text-white rounded-lg font-semibold text-lg hover:opacity-90 transition-opacity"
              style={{ backgroundColor: '{$primaryColor}' }}
            >
              {$ctaText}
            </button>
          </motion.div>
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
                'secondary' => '#8b5cf6',
            ],
            'fonts' => [
                'heading' => 'Inter',
                'body' => 'Inter',
            ],
            'texts' => [
                'title' => 'Witaj w naszym serwisie',
                'subtitle' => 'Tworzymy nowoczesne rozwiązania',
                'cta' => 'Rozpocznij',
            ],
        ];
    }
}
