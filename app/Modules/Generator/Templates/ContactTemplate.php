<?php

declare(strict_types=1);

namespace App\Modules\Generator\Templates;

/**
 * Szablon bazowy dla komponentu Contact.
 */
final class ContactTemplate
{
    /**
     * Zwróć kod komponentu Contact.
     *
     * @param  array<string, mixed>  $variables  Zmienne
     */
    public static function getCode(array $variables = []): string
    {
        $title = $variables['texts']['title'] ?? 'Skontaktuj się z nami';
        $subtitle = $variables['texts']['subtitle'] ?? 'Odpowiemy na wszystkie pytania';
        $primaryColor = $variables['colors']['primary'] ?? '#3b82f6';

        return <<<TSX
'use client';

import { useState } from 'react';

export default function Contact() {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    message: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // Handle form submission
    console.log('Form submitted:', formData);
  };

  return (
    <section className="py-20 bg-white dark:bg-gray-900">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        <div className="max-w-2xl mx-auto">
          <div className="text-center mb-12">
            <h2 className="text-3xl sm:text-4xl md:text-5xl font-bold mb-4">
              {$title}
            </h2>
            <p className="text-xl text-gray-600 dark:text-gray-300">
              {$subtitle}
            </p>
          </div>
          
          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label htmlFor="name" className="block text-sm font-medium mb-2">
                Imię i nazwisko
              </label>
              <input
                type="text"
                id="name"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-{$primaryColor} focus:border-transparent"
                required
              />
            </div>
            
            <div>
              <label htmlFor="email" className="block text-sm font-medium mb-2">
                Email
              </label>
              <input
                type="email"
                id="email"
                value={formData.email}
                onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-{$primaryColor} focus:border-transparent"
                required
              />
            </div>
            
            <div>
              <label htmlFor="message" className="block text-sm font-medium mb-2">
                Wiadomość
              </label>
              <textarea
                id="message"
                value={formData.message}
                onChange={(e) => setFormData({ ...formData, message: e.target.value })}
                rows={6}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-{$primaryColor} focus:border-transparent"
                required
              />
            </div>
            
            <button
              type="submit"
              className="w-full px-8 py-4 rounded-lg font-semibold text-lg text-white hover:opacity-90 transition-opacity"
              style={{ backgroundColor: '{$primaryColor}' }}
            >
              Wyślij wiadomość
            </button>
          </form>
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
                'title' => 'Skontaktuj się z nami',
                'subtitle' => 'Odpowiemy na wszystkie pytania',
            ],
        ];
    }
}
