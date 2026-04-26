'use client';

import Image from 'next/image';
import Link from 'next/link';
import { getAssetPath, MONDAY_RESERVATION_FORM_URL } from '@/lib/paths';
import type { Motorcycle } from '@/lib/api';

interface BikeCardProps {
  bike: Motorcycle;
}

export default function BikeCard({ bike }: BikeCardProps) {
  // Obsługuje zarówno main_image z API jak i images[] z mocka
  const mainImage = bike.main_image || bike.images?.[0] || { url: '/img/bikes/default.jpg', alt: bike.name };
  
  // URL jest już przekonwertowany przez mapApiMotorcycle (getStorageUrl)
  const imageUrl = mainImage.url;

  return (
    <div className="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-xl transition-shadow">
      <Link href={`/motocykle/${bike.slug}`}>
        <div className="relative w-full cursor-pointer bg-gray-100" style={{ aspectRatio: '3/2' }}>
          <Image
            src={imageUrl.startsWith('http') ? imageUrl : getAssetPath(imageUrl)}
            alt={mainImage.alt || bike.name}
            fill
            className="object-contain"
            unoptimized={imageUrl.startsWith('http')}
          />
          {!bike.available && (
            <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
              <span className="bg-red-600 text-white px-4 py-2 rounded-lg font-semibold">
                Niedostępny
              </span>
            </div>
          )}
        </div>
      </Link>
      <div className="p-6">
        <div className="flex items-start justify-between mb-2">
          <div>
            <h3 className="font-heading text-xl font-bold">{bike.name}</h3>
            <p className="text-gray-medium">{bike.brand.name}</p>
          </div>
          <span className="bg-accent-red text-white px-3 py-1 rounded text-sm font-semibold">
            {bike.category.name}
          </span>
        </div>
        <div className="flex items-center gap-4 mb-4 text-sm text-gray-medium flex-wrap">
          {(bike.specs?.engine || bike.specifications?.engine || bike.engine_capacity) && (
            <>
              <span>{bike.specs?.engine || bike.specifications?.engine || `${bike.engine_capacity}cc`}</span>
              <span>•</span>
            </>
          )}
          {(bike.specs?.power || bike.specifications?.power) && (
            <>
              <span>{bike.specs?.power || bike.specifications?.power}</span>
              <span>•</span>
            </>
          )}
          <span>Rok {bike.year}</span>
        </div>
        {bike.features.length > 0 && (
          <div className="mb-4 flex flex-wrap gap-2">
            {bike.features.slice(0, 3).map((feature, index) => (
              <span
                key={index}
                className="bg-gray-light text-gray-dark px-2 py-1 rounded text-xs"
              >
                {feature}
              </span>
            ))}
          </div>
        )}
        <div className="flex items-center justify-between">
          <div>
            <div className="text-2xl font-bold text-accent-red">{bike.price_per_day} zł</div>
            <div className="text-sm text-gray-medium">za dzień</div>
          </div>
          <div className="flex gap-2">
            <Link
              href={`/motocykle/${bike.slug}`}
              className="bg-gray-800 text-white px-4 py-2 rounded-lg font-semibold hover:bg-gray-900 transition-colors"
            >
              Szczegóły
            </Link>
            <a
              href={bike.available ? MONDAY_RESERVATION_FORM_URL : undefined}
              target="_blank"
              rel="noopener noreferrer"
              className={`inline-block px-6 py-2 rounded-lg font-semibold transition-colors text-center ${
                bike.available
                  ? 'bg-accent-red text-white hover:bg-red-700 cursor-pointer'
                  : 'bg-gray-300 text-gray-500 cursor-not-allowed pointer-events-none'
              }`}
            >
              Rezerwuj
            </a>
          </div>
        </div>
      </div>
    </div>
  );
}
