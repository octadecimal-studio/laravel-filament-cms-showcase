'use client';

import { useState, useCallback, useEffect } from 'react';
import Image from 'next/image';
import { getAssetPath } from '@/lib/paths';
import type { GalleryData, Motorcycle } from '@/lib/api';

interface GalleryProps {
  gallery: GalleryData;
  bikes: Motorcycle[];
}

function imageSrc(url: string) {
  return url.startsWith('http') ? url : getAssetPath(url);
}

export default function Gallery({ gallery, bikes }: GalleryProps) {
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

  // Obrazy z API galerii (CMS) lub z motocykli
  const allImages = gallery.images?.length
    ? gallery.images.map((img) => ({ url: img.url, alt: img.alt }))
    : bikes.flatMap((bike) =>
        bike.images.map((img) => ({ url: img.url, alt: img.alt }))
      );

  const openLightbox = useCallback((index: number) => {
    setLightboxIndex(index);
  }, []);

  const closeLightbox = useCallback(() => {
    setLightboxIndex(null);
  }, []);

  const goPrev = useCallback(() => {
    if (lightboxIndex === null) return;
    setLightboxIndex(lightboxIndex <= 0 ? allImages.length - 1 : lightboxIndex - 1);
  }, [lightboxIndex, allImages.length]);

  const goNext = useCallback(() => {
    if (lightboxIndex === null) return;
    setLightboxIndex(lightboxIndex >= allImages.length - 1 ? 0 : lightboxIndex + 1);
  }, [lightboxIndex, allImages.length]);

  useEffect(() => {
    if (lightboxIndex === null) return;
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') closeLightbox();
      else if (e.key === 'ArrowLeft') goPrev();
      else if (e.key === 'ArrowRight') goNext();
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [lightboxIndex, closeLightbox, goPrev, goNext]);

  const current = lightboxIndex !== null ? allImages[lightboxIndex] : null;

  return (
    <section className="py-20 bg-white">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
            {gallery.title}
          </h2>
          <p className="text-lg text-gray-medium max-w-2xl mx-auto">
            {gallery.subtitle}
          </p>
        </div>

        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          {allImages.map((image, index) => (
            <div
              key={index}
              className="relative aspect-square cursor-pointer overflow-hidden rounded-lg hover:opacity-90 transition-opacity"
              onClick={() => openLightbox(index)}
            >
              <Image
                src={imageSrc(image.url)}
                alt={image.alt || `Galeria ${index + 1}`}
                fill
                className="object-cover"
              />
            </div>
          ))}
        </div>

        {/* Lightbox z przewijaniem */}
        {current && lightboxIndex !== null && (
          <div
            className="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4"
            onClick={closeLightbox}
            role="dialog"
            aria-modal="true"
            aria-label="Przeglądarka galerii"
          >
            <div
              className="relative max-w-5xl w-full max-h-[90vh] flex items-center justify-center gap-2"
              onClick={(e) => e.stopPropagation()}
            >
              {allImages.length > 1 && (
                <button
                  type="button"
                  onClick={goPrev}
                  className="absolute left-0 md:-left-14 top-1/2 -translate-y-1/2 z-10 text-white bg-black/50 hover:bg-black/70 rounded-full w-12 h-12 flex items-center justify-center text-2xl font-bold shrink-0"
                  aria-label="Poprzednie zdjęcie"
                >
                  ‹
                </button>
              )}

              <div className="relative flex-1 flex items-center justify-center">
                <Image
                  src={imageSrc(current.url)}
                  alt={current.alt || `Zdjęcie ${lightboxIndex + 1} z ${allImages.length}`}
                  width={1200}
                  height={800}
                  className="object-contain max-h-[90vh] rounded"
                />
              </div>

              {allImages.length > 1 && (
                <button
                  type="button"
                  onClick={goNext}
                  className="absolute right-0 md:-right-14 top-1/2 -translate-y-1/2 z-10 text-white bg-black/50 hover:bg-black/70 rounded-full w-12 h-12 flex items-center justify-center text-2xl font-bold shrink-0"
                  aria-label="Następne zdjęcie"
                >
                  ›
                </button>
              )}

              <button
                type="button"
                onClick={closeLightbox}
                className="absolute top-0 right-0 md:top-4 md:right-4 text-white text-2xl font-bold bg-black/50 hover:bg-black/70 rounded-full w-10 h-10 flex items-center justify-center shrink-0"
                aria-label="Zamknij"
              >
                ×
              </button>
            </div>

            {allImages.length > 1 && (
              <p className="absolute bottom-4 left-1/2 -translate-x-1/2 text-white/80 text-sm">
                {lightboxIndex + 1} / {allImages.length}
              </p>
            )}
          </div>
        )}
      </div>
    </section>
  );
}
