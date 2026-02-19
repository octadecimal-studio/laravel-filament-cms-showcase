'use client';

import { useState } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { FiArrowLeft, FiX } from 'react-icons/fi';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import { getAssetPath, MONDAY_RESERVATION_FORM_URL } from '@/lib/paths';
import type { Motorcycle, SiteData, NavigationData, FooterData, ContactData } from '@/lib/api';

interface MotorcycleDetailClientProps {
  motorcycle: Motorcycle;
  site: SiteData;
  navigation: NavigationData;
  footer: FooterData;
  contact: ContactData;
}

export default function MotorcycleDetailClient({
  motorcycle,
  site,
  navigation,
  footer,
  contact,
}: MotorcycleDetailClientProps) {
  const [lightboxImage, setLightboxImage] = useState<string | null>(null);
  const [lightboxIndex, setLightboxIndex] = useState(0);

  // Wszystkie obrazy: main_image + gallery
  const allImages = motorcycle.images || (motorcycle.main_image ? [motorcycle.main_image] : []);
  
  const openLightbox = (index: number) => {
    setLightboxIndex(index);
    setLightboxImage(allImages[index]?.url || null);
  };
  
  const nextImage = () => {
    const next = (lightboxIndex + 1) % allImages.length;
    openLightbox(next);
  };
  
  const prevImage = () => {
    const prev = (lightboxIndex - 1 + allImages.length) % allImages.length;
    openLightbox(prev);
  };

  return (
    <>
      <Header site={site} navigation={navigation} />
      
      <main className="min-h-screen bg-gray-light pt-20">
        <div className="container mx-auto px-4 py-8">
          {/* Przycisk powrotu */}
          <Link
            href="/#motocykle"
            className="inline-flex items-center gap-2 text-gray-dark hover:text-accent-red transition-colors mb-6"
          >
            <FiArrowLeft size={20} />
            <span>Powrót do listy motocykli</span>
          </Link>

          {/* Główna sekcja */}
          <div className="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-0">
              {/* Galeria zdjęć */}
              <div className="relative">
                {allImages.length > 0 ? (
                  <>
                    {/* Główne zdjęcie - proporcja 3:2, obrazek widoczny w całości */}
                    <div className="relative w-full bg-gray-100" style={{ aspectRatio: '3/2' }}>
                      <Image
                        src={allImages[0].url.startsWith('http') ? allImages[0].url : getAssetPath(allImages[0].url)}
                        alt={allImages[0].alt || motorcycle.name}
                        fill
                        className="object-contain cursor-pointer"
                        onClick={() => openLightbox(0)}
                        unoptimized={allImages[0].url.startsWith('http')}
                        priority
                      />
                    </div>
                    
                    {/* Miniaturki (jeśli więcej niż 1 zdjęcie) */}
                    {allImages.length > 1 && (
                      <div className="grid grid-cols-4 gap-2 p-4 bg-gray-50">
                        {allImages.slice(0, 4).map((img, index) => (
                          <div
                            key={index}
                            className="relative h-20 cursor-pointer hover:opacity-75 transition-opacity"
                            onClick={() => openLightbox(index)}
                          >
                            <Image
                              src={img.url.startsWith('http') ? img.url : getAssetPath(img.url)}
                              alt={img.alt || motorcycle.name}
                              fill
                              className="object-cover rounded"
                              unoptimized={img.url.startsWith('http')}
                            />
                          </div>
                        ))}
                        {allImages.length > 4 && (
                          <div className="relative h-20 bg-gray-200 rounded flex items-center justify-center cursor-pointer hover:bg-gray-300 transition-colors">
                            <span className="text-gray-600 font-semibold">
                              +{allImages.length - 4}
                            </span>
                          </div>
                        )}
                      </div>
                    )}
                  </>
                ) : (
                  <div className="h-96 lg:h-[600px] bg-gray-200 flex items-center justify-center">
                    <span className="text-gray-400">Brak zdjęć</span>
                  </div>
                )}
              </div>

              {/* Informacje o motocyklu */}
              <div className="p-6 lg:p-12 flex flex-col justify-between">
                <div>
                  <div className="flex items-center gap-3 mb-4">
                    <span className="bg-accent-red text-white px-3 py-1 rounded text-sm font-semibold">
                      {motorcycle.category.name}
                    </span>
                    {motorcycle.brand.name && (
                      <span className="text-gray-medium">{motorcycle.brand.name}</span>
                    )}
                  </div>
                  
                  <h1 className="font-heading text-4xl md:text-5xl font-bold mb-4">
                    {motorcycle.name}
                  </h1>
                  
                  {/* Specyfikacje */}
                  <div className="grid grid-cols-2 gap-4 mb-6">
                    {motorcycle.specs?.engine && (
                      <div>
                        <span className="text-gray-medium text-sm">Silnik</span>
                        <p className="font-semibold">{motorcycle.specs.engine}</p>
                      </div>
                    )}
                    {motorcycle.specs?.power && (
                      <div>
                        <span className="text-gray-medium text-sm">Moc</span>
                        <p className="font-semibold">{motorcycle.specs.power}</p>
                      </div>
                    )}
                    {motorcycle.specs?.weight && (
                      <div>
                        <span className="text-gray-medium text-sm">Waga</span>
                        <p className="font-semibold">{motorcycle.specs.weight}</p>
                      </div>
                    )}
                    {motorcycle.year && (
                      <div>
                        <span className="text-gray-medium text-sm">Rok</span>
                        <p className="font-semibold">{motorcycle.year}</p>
                      </div>
                    )}
                  </div>

                  {/* Opis */}
                  {motorcycle.description && (
                    <div className="mb-6">
                      <h2 className="font-heading text-xl font-bold mb-2">Opis</h2>
                      <div 
                        className="text-gray-medium [&_p]:mb-4 [&_br]:block"
                        dangerouslySetInnerHTML={{ __html: motorcycle.description }}
                      />
                    </div>
                  )}

                  {/* Cennik */}
                  <div className="bg-gray-50 rounded-lg p-6 mb-6">
                    <h3 className="font-heading text-xl font-bold mb-4">Cennik</h3>
                    <div className="space-y-2">
                      <div className="flex justify-between">
                        <span className="text-gray-medium">Za dzień:</span>
                        <span className="font-bold text-accent-red text-lg">{motorcycle.price_per_day} zł</span>
                      </div>
                      {motorcycle.price_per_week && (
                        <div className="flex justify-between">
                          <span className="text-gray-medium">Za tydzień:</span>
                          <span className="font-semibold">{motorcycle.price_per_week} zł</span>
                        </div>
                      )}
                      {motorcycle.price_per_month && (
                        <div className="flex justify-between">
                          <span className="text-gray-medium">Za miesiąc:</span>
                          <span className="font-semibold">{motorcycle.price_per_month} zł</span>
                        </div>
                      )}
                      <div className="flex justify-between pt-2 border-t border-gray-300">
                        <span className="text-gray-medium">Kaucja:</span>
                        <span className="font-semibold">{motorcycle.deposit} zł</span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Przyciski akcji */}
                <div className="flex flex-col sm:flex-row gap-4">
                  <a
                    href={motorcycle.available ? MONDAY_RESERVATION_FORM_URL : undefined}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={motorcycle.available ? 'Zarezerwuj teraz' : 'Motocykl niedostępny do rezerwacji'}
                    className={`px-8 py-4 rounded-lg font-semibold text-lg transition-colors text-center ${
                      motorcycle.available
                        ? 'bg-accent-red text-white hover:bg-red-700 cursor-pointer'
                        : 'bg-gray-300 text-gray-500 cursor-not-allowed pointer-events-none'
                    }`}
                  >
                    Zarezerwuj teraz
                  </a>
                  <Link
                    href="/#motocykle"
                    className="bg-gray-800 text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-900 transition-colors text-center"
                  >
                    Zobacz inne modele
                  </Link>
                </div>
              </div>
            </div>
          </div>

          {/* Pełna galeria (jeśli więcej niż 4 zdjęcia) */}
          {allImages.length > 4 && (
            <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
              <h2 className="font-heading text-2xl font-bold mb-6">Galeria zdjęć</h2>
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                {allImages.map((img, index) => (
                  <div
                    key={index}
                    className="relative h-48 cursor-pointer hover:opacity-75 transition-opacity rounded-lg overflow-hidden"
                    onClick={() => openLightbox(index)}
                  >
                    <Image
                      src={img.url.startsWith('http') ? img.url : getAssetPath(img.url)}
                      alt={img.alt || `${motorcycle.name} - zdjęcie ${index + 1}`}
                      fill
                      className="object-cover"
                      unoptimized={img.url.startsWith('http')}
                    />
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Sekcja rezerwacji */}
          <div id="rezerwacja" className="bg-white rounded-xl shadow-lg p-6 lg:p-12 mb-8">
            <div className="max-w-2xl mx-auto text-center">
              <h2 className="font-heading text-3xl font-bold mb-2">
                Zarezerwuj {motorcycle.brand.name} {motorcycle.name}
              </h2>
              <p className="text-gray-medium mb-8">
                Kliknij poniżej, aby wypełnić formularz rezerwacji
              </p>
              <a
                href={MONDAY_RESERVATION_FORM_URL}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-block bg-accent-red text-white px-10 py-4 rounded-lg font-semibold text-lg hover:bg-red-700 transition-colors"
              >
                Rezerwuj teraz
              </a>
            </div>
          </div>
        </div>
      </main>

      <Footer site={site} footer={footer} contact={contact} />

      {/* Lightbox */}
      {lightboxImage && (
        <div
          className="fixed inset-0 bg-black/95 z-50 flex items-center justify-center p-4"
          style={{ animation: 'fadeIn 0.3s ease-in' }}
          onClick={() => setLightboxImage(null)}
        >
          <button
            className="absolute top-4 right-4 text-white hover:text-accent-red transition-colors z-10"
            onClick={() => setLightboxImage(null)}
            aria-label="Zamknij galerię"
          >
            <FiX size={32} />
          </button>
          
          {allImages.length > 1 && (
            <>
              <button
                className="absolute left-4 text-white hover:text-accent-red transition-colors z-10"
                onClick={(e) => {
                  e.stopPropagation();
                  prevImage();
                }}
                aria-label="Poprzednie zdjęcie"
              >
                <FiArrowLeft size={32} />
              </button>
              <button
                className="absolute right-4 text-white hover:text-accent-red transition-colors z-10"
                onClick={(e) => {
                  e.stopPropagation();
                  nextImage();
                }}
                aria-label="Następne zdjęcie"
              >
                <FiArrowLeft size={32} className="rotate-180" />
              </button>
            </>
          )}
          
          <div
            className="relative max-w-7xl max-h-full"
            style={{ animation: 'scaleIn 0.3s ease-out' }}
            onClick={(e) => e.stopPropagation()}
          >
            <Image
              src={lightboxImage.startsWith('http') ? lightboxImage : getAssetPath(lightboxImage)}
              alt={`${motorcycle.name} - zdjęcie ${lightboxIndex + 1}`}
              width={1200}
              height={800}
              className="max-w-full max-h-[90vh] object-contain rounded-lg"
              unoptimized={lightboxImage.startsWith('http')}
            />
            {allImages.length > 1 && (
              <div className="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-white text-sm">
                {lightboxIndex + 1} / {allImages.length}
              </div>
            )}
          </div>
        </div>
      )}
    </>
  );
}
