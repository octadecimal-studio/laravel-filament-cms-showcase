'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { FiArrowLeft, FiX } from 'react-icons/fi';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import { getAssetPath, MONDAY_RESERVATION_FORM_URL } from '@/lib/paths';
import type { Motorcycle, MotorcycleImage, SiteData, NavigationData, FooterData, ContactData } from '@/lib/api';

const TENANT_ID = process.env.NEXT_PUBLIC_TENANT_ID || '';
const FETCH_OPTS: RequestInit = { cache: 'no-store', headers: { Accept: 'application/json' } };

function freshApiUrl(endpoint: string): string {
  const sp = new URLSearchParams();
  if (TENANT_ID) sp.set('tenant_id', TENANT_ID);
  sp.set('_t', String(Date.now()));
  return `/api/motorent${endpoint}?${sp}`;
}

function getStorageUrl(path: string | null | undefined): string {
  if (!path) return '/img/placeholder.jpg';
  if (path.startsWith('http')) return path;
  return path;
}

function mapApiMotorcycle(m: any): Motorcycle {
  const mainImage = m.main_image ? { ...m.main_image, url: getStorageUrl(m.main_image.url) } : undefined;
  const gallery: MotorcycleImage[] = (m.gallery || []).map((img: any) => ({
    id: img.id, url: getStorageUrl(img.url), alt: img.alt_text || img.alt || m.name,
  }));
  const allImages = mainImage ? [mainImage, ...gallery] : gallery;
  return {
    id: m.id, name: m.name, slug: m.slug, brand: m.brand, category: m.category,
    main_image: mainImage, price_per_day: m.price_per_day, price_per_week: m.price_per_week,
    price_per_month: m.price_per_month, deposit: m.deposit,
    specs: {
      engine: m.specifications?.engine || `${m.engine_capacity}cc`,
      power: m.specifications?.power || '', weight: m.specifications?.weight || '',
      seat_height: '', seats: m.specifications?.seats,
    },
    images: allImages, gallery, features: [],
    available: m.available !== undefined ? m.available : true,
    featured: m.featured || false, year: m.year,
    engine_capacity: m.engine_capacity, description: m.description,
    specifications: m.specifications,
  };
}

/**
 * Fallback page for motorcycle detail — served via .htaccess rewrite
 * when no pre-built static page exists for a given slug.
 * Reads slug from window.location.pathname and fetches everything client-side.
 */
export default function MotorcycleFallbackPage() {
  const [motorcycle, setMotorcycle] = useState<Motorcycle | null>(null);
  const [loading, setLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);
  const [lightboxImage, setLightboxImage] = useState<string | null>(null);
  const [lightboxIndex, setLightboxIndex] = useState(0);

  useEffect(() => {
    let cancelled = false;
    async function load() {
      // Extract slug from URL: /motocykle/some-slug → some-slug
      const parts = window.location.pathname.split('/').filter(Boolean);
      const slug = parts.length >= 2 && parts[0] === 'motocykle' ? parts[1] : null;

      if (!slug || slug === 'csr-detail') {
        setNotFound(true);
        setLoading(false);
        return;
      }

      try {
        const res = await fetch(freshApiUrl(`/motorcycles/${slug}`), FETCH_OPTS);
        if (!res.ok || cancelled) {
          if (!cancelled) setNotFound(true);
          return;
        }
        const json = await res.json();
        const data = json.data || json;
        if (!cancelled && data && data.id) {
          setMotorcycle(mapApiMotorcycle(data));
        } else if (!cancelled) {
          setNotFound(true);
        }
      } catch {
        if (!cancelled) setNotFound(true);
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    load();
    return () => { cancelled = true; };
  }, []);

  const allImages = motorcycle?.images || (motorcycle?.main_image ? [motorcycle.main_image] : []);

  const openLightbox = (index: number) => {
    setLightboxIndex(index);
    setLightboxImage(allImages[index]?.url || null);
  };
  const nextImage = () => openLightbox((lightboxIndex + 1) % allImages.length);
  const prevImage = () => openLightbox((lightboxIndex - 1 + allImages.length) % allImages.length);

  // Loading state
  if (loading) {
    return (
      <main className="min-h-screen bg-gray-light pt-20 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-accent-red mx-auto mb-4" />
          <p className="text-gray-medium">Ładowanie...</p>
        </div>
      </main>
    );
  }

  // Not found
  if (notFound || !motorcycle) {
    return (
      <main className="min-h-screen bg-gray-light pt-20 flex items-center justify-center">
        <div className="text-center">
          <h1 className="font-heading text-4xl font-bold mb-4">Nie znaleziono motocykla</h1>
          <p className="text-gray-medium mb-8">Sprawdź adres URL lub wróć do listy motocykli.</p>
          <Link
            href="/#motocykle"
            className="inline-flex items-center gap-2 bg-accent-red text-white px-8 py-4 rounded-lg font-semibold hover:bg-red-700 transition-colors"
          >
            <FiArrowLeft size={20} />
            Powrót do listy motocykli
          </Link>
        </div>
      </main>
    );
  }

  return (
    <>
      <main className="min-h-screen bg-gray-light pt-20">
        <div className="container mx-auto px-4 py-8">
          <Link
            href="/#motocykle"
            className="inline-flex items-center gap-2 text-gray-dark hover:text-accent-red transition-colors mb-6"
          >
            <FiArrowLeft size={20} />
            <span>Powrót do listy motocykli</span>
          </Link>

          <div className="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-0">
              {/* Gallery */}
              <div className="relative">
                {allImages.length > 0 ? (
                  <>
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
                            <span className="text-gray-600 font-semibold">+{allImages.length - 4}</span>
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

              {/* Info */}
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
                  <h1 className="font-heading text-4xl md:text-5xl font-bold mb-4">{motorcycle.name}</h1>
                  <div className="grid grid-cols-2 gap-4 mb-6">
                    {motorcycle.specs?.engine && (
                      <div><span className="text-gray-medium text-sm">Silnik</span><p className="font-semibold">{motorcycle.specs.engine}</p></div>
                    )}
                    {motorcycle.specs?.power && (
                      <div><span className="text-gray-medium text-sm">Moc</span><p className="font-semibold">{motorcycle.specs.power}</p></div>
                    )}
                    {motorcycle.specs?.weight && (
                      <div><span className="text-gray-medium text-sm">Waga</span><p className="font-semibold">{motorcycle.specs.weight}</p></div>
                    )}
                    {motorcycle.year && (
                      <div><span className="text-gray-medium text-sm">Rok</span><p className="font-semibold">{motorcycle.year}</p></div>
                    )}
                  </div>
                  {motorcycle.description && (
                    <div className="mb-6">
                      <h2 className="font-heading text-xl font-bold mb-2">Opis</h2>
                      <div className="text-gray-medium [&_p]:mb-4 [&_br]:block" dangerouslySetInnerHTML={{ __html: motorcycle.description }} />
                    </div>
                  )}
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
                <div className="flex flex-col sm:flex-row gap-4">
                  <a
                    href={motorcycle.available ? MONDAY_RESERVATION_FORM_URL : undefined}
                    target="_blank"
                    rel="noopener noreferrer"
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

          {/* Full gallery */}
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

          {/* Reservation CTA */}
          <div className="bg-white rounded-xl shadow-lg p-6 lg:p-12 mb-8">
            <div className="max-w-2xl mx-auto text-center">
              <h2 className="font-heading text-3xl font-bold mb-2">
                Zarezerwuj {motorcycle.brand.name} {motorcycle.name}
              </h2>
              <p className="text-gray-medium mb-8">Kliknij poniżej, aby wypełnić formularz rezerwacji</p>
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

      {/* Lightbox */}
      {lightboxImage && (
        <div
          className="fixed inset-0 bg-black/95 z-50 flex items-center justify-center p-4"
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
                onClick={(e) => { e.stopPropagation(); prevImage(); }}
                aria-label="Poprzednie zdjęcie"
              >
                <FiArrowLeft size={32} />
              </button>
              <button
                className="absolute right-4 text-white hover:text-accent-red transition-colors z-10"
                onClick={(e) => { e.stopPropagation(); nextImage(); }}
                aria-label="Następne zdjęcie"
              >
                <FiArrowLeft size={32} className="rotate-180" />
              </button>
            </>
          )}
          <div onClick={(e) => e.stopPropagation()}>
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
