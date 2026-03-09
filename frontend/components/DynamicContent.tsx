'use client';

import { useState, useEffect } from 'react';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import Hero from '@/components/sections/Hero';
import WhyUs from '@/components/sections/WhyUs';
import Fleet from '@/components/sections/Fleet';
import HowItWorks from '@/components/sections/HowItWorks';
import Pricing from '@/components/sections/Pricing';
import Terms from '@/components/sections/Terms';
import Gallery from '@/components/sections/Gallery';
import Testimonials from '@/components/sections/Testimonials';
import Location from '@/components/sections/Location';
import ContactForm from '@/components/sections/ContactForm';
import FloatingActions from '@/components/FloatingActions';
import type {
  SiteData, NavigationData, HeroData, WhyUsData, FleetData,
  HowItWorksData, PricingData, TermsData, GalleryData,
  TestimonialsData, LocationData, ContactData, FooterData,
  ReservationSettings, Motorcycle, MotorcycleImage,
} from '@/lib/api';

const TENANT_ID = process.env.NEXT_PUBLIC_TENANT_ID || '';

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
  const mainImage = m.main_image
    ? { ...m.main_image, url: getStorageUrl(m.main_image.url) }
    : undefined;
  const gallery: MotorcycleImage[] = (m.gallery || []).map((img: any) => ({
    id: img.id,
    url: getStorageUrl(img.url),
    alt: img.alt_text || img.alt || m.name,
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

interface AllContent {
  site: SiteData;
  navigation: NavigationData;
  hero: HeroData;
  whyUs: WhyUsData;
  fleet: FleetData;
  howItWorks: HowItWorksData;
  pricing: PricingData;
  terms: TermsData;
  gallery: GalleryData;
  testimonials: TestimonialsData;
  location: LocationData;
  contact: ContactData;
  footer: FooterData;
  reservationSettings: ReservationSettings;
}

interface DynamicContentProps {
  initialContent: AllContent;
  initialBikes: Motorcycle[];
  totalBikes: number;
}

export default function DynamicContent({
  initialContent,
  initialBikes,
  totalBikes,
}: DynamicContentProps) {
  const [content, setContent] = useState(initialContent);
  const [bikes, setBikes] = useState<Motorcycle[]>(initialBikes);
  const [bikeCount, setBikeCount] = useState(totalBikes);

  // CSR: fetch fresh motorcycle data on mount so CMS changes appear immediately
  useEffect(() => {
    let cancelled = false;
    async function refreshBikes() {
      try {
        const res = await fetch(freshApiUrl('/motorcycles'), {
          cache: 'no-store',
          headers: { Accept: 'application/json' },
        });
        if (!res.ok || cancelled) return;
        const json = await res.json();
        const freshBikes = (json.data || []).map(mapApiMotorcycle);
        if (!cancelled && freshBikes.length > 0) {
          setBikes(freshBikes);
          setBikeCount(freshBikes.length);
        }
      } catch {
        // Keep initial (build-time) data on error
      }
    }
    refreshBikes();
    return () => { cancelled = true; };
  }, []);

  // CSR: fetch fresh gallery data on mount
  useEffect(() => {
    let cancelled = false;
    async function refreshGallery() {
      try {
        const res = await fetch(freshApiUrl('/gallery'), {
          cache: 'no-store',
          headers: { Accept: 'application/json' },
        });
        if (!res.ok || cancelled) return;
        const json = await res.json();
        const data = json.data || {};
        const images = (data.images || []).map((img: { id?: string; url: string; alt: string }) => ({
          id: img.id,
          url: img.url.startsWith('http') ? img.url : getStorageUrl(img.url),
          alt: img.alt || '',
        }));
        if (!cancelled && images.length > 0) {
          setContent(prev => ({
            ...prev,
            gallery: {
              title: data.title || prev.gallery.title,
              subtitle: data.subtitle || prev.gallery.subtitle,
              images,
            },
          }));
        }
      } catch {
        // Keep initial (build-time) data on error
      }
    }
    refreshGallery();
    return () => { cancelled = true; };
  }, []);

  return (
    <main className="min-h-screen">
      <Header site={content.site} navigation={content.navigation} />
      <Hero hero={content.hero} />
      <HowItWorks howItWorks={content.howItWorks} />
      <Fleet
        fleet={content.fleet}
        initialBikes={bikes}
        totalBikes={bikeCount}
      />
      <WhyUs whyUs={content.whyUs} />
      <Pricing pricing={content.pricing} bikes={bikes} />
      <Terms terms={content.terms} />
      {/* Gallery ukryta na życzenie klienta — KML-0035 */}
      {/* <Gallery gallery={content.gallery} bikes={bikes} /> */}
      <Testimonials testimonials={content.testimonials} />
      <Location location={content.location} contact={content.contact} />
      <ContactForm
        contact={content.contact}
        reservationSettings={content.reservationSettings}
      />
      <Footer
        site={content.site}
        footer={content.footer}
        contact={content.contact}
      />
      <FloatingActions
        footer={content.footer}
        contact={content.contact}
        reservationSettings={content.reservationSettings}
      />
    </main>
  );
}
