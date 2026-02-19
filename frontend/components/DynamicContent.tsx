'use client';

import { useEffect, useState } from 'react';
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
import mockContent from '@/data/mock-api-v2.json';
import type {
  SiteData, NavigationData, HeroData, WhyUsData, FleetData,
  HowItWorksData, PricingData, TermsData, GalleryData,
  TestimonialsData, LocationData, ContactData, FooterData,
  ReservationSettings, Motorcycle, Feature, Step, Testimonial,
} from '@/lib/api';

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

// Tenant ID baked in at build time via NEXT_PUBLIC_*
const TENANT_ID = process.env.NEXT_PUBLIC_TENANT_ID || '';

const FETCH_OPTS: RequestInit = {
  cache: 'no-store',
  headers: { Accept: 'application/json' },
};

function apiUrl(endpoint: string, params?: Record<string, string>): string {
  const base = `/api/motorent${endpoint}`;
  const sp = new URLSearchParams();
  if (TENANT_ID) sp.set('tenant_id', TENANT_ID);
  sp.set('_t', String(Date.now())); // cache-buster
  if (params) Object.entries(params).forEach(([k, v]) => sp.set(k, v));
  const qs = sp.toString();
  return qs ? `${base}?${qs}` : base;
}

function getStorageUrl(path: string | null | undefined): string {
  if (!path) return '/img/placeholder.jpg';
  if (path.startsWith('http')) return path;
  // /storage/ paths work as relative URLs on the same domain
  return path;
}

function mapApiMotorcycle(apiMoto: any): Motorcycle {
  const mainImage = apiMoto.main_image
    ? { ...apiMoto.main_image, url: getStorageUrl(apiMoto.main_image.url) }
    : undefined;

  const galleryImages = (apiMoto.gallery || []).map((img: any) => ({
    id: img.id,
    url: getStorageUrl(img.url),
    alt: img.alt_text || img.alt || apiMoto.name,
  }));

  const allImages = mainImage ? [mainImage, ...galleryImages] : galleryImages;

  return {
    id: apiMoto.id,
    name: apiMoto.name,
    slug: apiMoto.slug,
    brand: apiMoto.brand,
    category: apiMoto.category,
    main_image: mainImage,
    price_per_day: apiMoto.price_per_day,
    price_per_week: apiMoto.price_per_week,
    price_per_month: apiMoto.price_per_month,
    deposit: apiMoto.deposit,
    specs: {
      engine: apiMoto.specifications?.engine || `${apiMoto.engine_capacity}cc`,
      power: apiMoto.specifications?.power || '',
      weight: apiMoto.specifications?.weight || '',
      seat_height: '',
      seats: apiMoto.specifications?.seats,
    },
    images: allImages,
    gallery: galleryImages,
    features: [],
    available: apiMoto.available !== undefined ? apiMoto.available : true,
    featured: apiMoto.featured || false,
    year: apiMoto.year,
    engine_capacity: apiMoto.engine_capacity,
    description: apiMoto.description,
    specifications: apiMoto.specifications,
  };
}

export default function DynamicContent({
  initialContent,
  initialBikes,
  totalBikes,
}: DynamicContentProps) {
  const [content, setContent] = useState<AllContent>(initialContent);
  const [bikes, setBikes] = useState<Motorcycle[]>(initialBikes);
  const [bikeCount, setBikeCount] = useState(totalBikes);

  useEffect(() => {
    let cancelled = false;

    async function refresh() {
      try {
        // Fetch all 9 endpoints in parallel
        const [
          siteRes, featuresRes, stepsRes, pricingRes,
          conditionsRes, galleryRes, testimonialsRes,
          bikesRes, reservationRes,
        ] = await Promise.all([
          fetch(apiUrl('/site-setting'), FETCH_OPTS),
          fetch(apiUrl('/features'), FETCH_OPTS),
          fetch(apiUrl('/process-steps'), FETCH_OPTS),
          fetch(apiUrl('/pricing'), FETCH_OPTS),
          fetch(apiUrl('/rental-conditions'), FETCH_OPTS),
          fetch(apiUrl('/gallery'), FETCH_OPTS),
          fetch(apiUrl('/testimonials'), FETCH_OPTS),
          fetch(apiUrl('/motorcycles', { per_page: '20' }), FETCH_OPTS),
          fetch(apiUrl('/reservation-settings'), FETCH_OPTS),
        ]);

        if (cancelled) return;

        // Parse each response (null on failure — individual endpoints can fail gracefully)
        const [
          siteJson, featuresJson, stepsJson, pricingJson,
          conditionsJson, galleryJson, testimonialsJson,
          bikesJson, reservationJson,
        ] = await Promise.all([
          siteRes.ok ? siteRes.json() : null,
          featuresRes.ok ? featuresRes.json() : null,
          stepsRes.ok ? stepsRes.json() : null,
          pricingRes.ok ? pricingRes.json() : null,
          conditionsRes.ok ? conditionsRes.json() : null,
          galleryRes.ok ? galleryRes.json() : null,
          testimonialsRes.ok ? testimonialsRes.json() : null,
          bikesRes.ok ? bikesRes.json() : null,
          reservationRes.ok ? reservationRes.json() : null,
        ]);

        if (cancelled) return;

        // ── Site ──────────────────────────────────────────────
        const setting = siteJson?.data;
        let freshSite: SiteData | null = null;
        if (setting) {
          freshSite = {
            name: setting.site_title || initialContent.site.name,
            tagline: mockContent.content.site.tagline,
            logo: getStorageUrl(setting.logo?.url || setting.logo),
            description: setting.site_description,
            aboutUsContent: setting.about_us_content || null,
            regulaminContent: setting.regulamin_content || null,
            politykaContent: setting.polityka_prywatnosci_content || null,
            phone: setting.contact_phone,
            email: setting.contact_email,
            address: setting.address,
            openingHours: setting.opening_hours,
            mapCoordinates: setting.map_coordinates,
            googleAnalyticsCode: setting.google_analytics_code || null,
            locationTitle: setting.location_title || null,
            locationDescription: setting.location_description || null,
          };
        }

        // ── Hero ──────────────────────────────────────────────
        const freshHero: HeroData | null = freshSite
          ? { ...initialContent.hero, description: freshSite.description }
          : null;

        // ── WhyUs ─────────────────────────────────────────────
        let freshWhyUs: WhyUsData | null = null;
        if (featuresJson) {
          const features: Feature[] = (featuresJson.data || [])
            .sort((a: any, b: any) => (a.order || 0) - (b.order || 0))
            .map((f: any) => ({
              id: f.id,
              title: f.title,
              description: f.description,
              icon: getStorageUrl(f.icon?.url),
              order: f.order,
            }));
          freshWhyUs = {
            title: mockContent.content.sections.whyUs.title,
            subtitle: mockContent.content.sections.whyUs.subtitle,
            aboutUsContent: freshSite?.aboutUsContent || initialContent.whyUs.aboutUsContent,
            features,
          };
        } else if (freshSite) {
          freshWhyUs = {
            ...initialContent.whyUs,
            aboutUsContent: freshSite.aboutUsContent || initialContent.whyUs.aboutUsContent,
          };
        }

        // ── HowItWorks ───────────────────────────────────────
        let freshHowItWorks: HowItWorksData | null = null;
        if (stepsJson) {
          const now = new Date().toISOString();
          const steps: Step[] = (stepsJson.data || []).map((s: any) => ({
            number: s.step_number,
            title: s.title,
            description: s.description,
            icon: s.icon_name,
            published_at: s.published_at || now,
          }));
          const n = steps.length;
          const krokLabel = n === 1 ? 'w 1 kroku' : `w ${n} krokach`;
          freshHowItWorks = {
            title: mockContent.content.sections.howItWorks.title,
            subtitle: `Prosty proces rezerwacji ${krokLabel}`,
            steps,
          };
        }

        // ── Pricing ──────────────────────────────────────────
        let freshPricing: PricingData | null = null;
        if (pricingJson) {
          const d = pricingJson.data || {};
          freshPricing = {
            title: d.title || mockContent.content.sections.pricing.title,
            subtitle: d.subtitle || mockContent.content.sections.pricing.subtitle,
            motorcycles: (d.motorcycles || []).map((m: any) => ({
              id: m.id,
              name: m.name,
              slug: m.slug,
              price_per_day: m.price_per_day,
              price_per_week: m.price_per_week,
              price_per_month: m.price_per_month,
              deposit: m.deposit,
            })),
            notes: (d.notes || []).map((n: any) => ({ id: n.id, content: n.content })),
          };
        }

        // ── Terms ────────────────────────────────────────────
        let freshTerms: TermsData | null = null;
        if (conditionsJson) {
          freshTerms = {
            title: 'Warunki wypożyczenia',
            items: (conditionsJson.data || []).map((c: any) => ({
              title: c.title,
              icon: c.icon || undefined,
              description: c.description || undefined,
              points: [],
            })),
          };
        }

        // ── Gallery ──────────────────────────────────────────
        let freshGallery: GalleryData | null = null;
        if (galleryJson) {
          const gd = galleryJson.data || {};
          freshGallery = {
            title: gd.title || mockContent.content.sections.gallery.title,
            subtitle: gd.subtitle || mockContent.content.sections.gallery.subtitle,
            images: (gd.images || []).map((img: any) => ({
              id: img.id,
              url: getStorageUrl(img.url),
              alt: img.alt || '',
            })),
          };
        }

        // ── Testimonials ─────────────────────────────────────
        let freshTestimonials: TestimonialsData | null = null;
        if (testimonialsJson) {
          const items: Testimonial[] = (testimonialsJson.data || []).map(
            (t: any) => ({
              id: t.id,
              name: t.author_name,
              rating: t.rating,
              text: t.content,
              motorcycle: t.motorcycle,
            }),
          );
          freshTestimonials = {
            title: mockContent.content.sections.testimonials.title,
            subtitle: mockContent.content.sections.testimonials.subtitle,
            items,
          };
        }

        // ── Location ─────────────────────────────────────────
        const freshLocation: LocationData | null = freshSite
          ? {
              title:
                freshSite.locationTitle ||
                mockContent.content.sections.location.title,
              subtitle:
                freshSite.locationDescription ||
                mockContent.content.sections.location.subtitle,
            }
          : null;

        // ── Contact ──────────────────────────────────────────
        let freshContact: ContactData | null = null;
        if (freshSite) {
          const cm = mockContent.content.sections.contact;
          const addrParts = freshSite.address?.split('\n') || [];
          const street = addrParts[0] || cm.address.street;
          const cityZip =
            addrParts[1] || `${cm.address.zip} ${cm.address.city}`;
          const [zip, ...cityParts] = cityZip.split(' ').reverse();
          const city = cityParts.reverse().join(' ') || cm.address.city;

          const hoursParts =
            freshSite.openingHours?.split('\n').filter(Boolean) || [];

          freshContact = {
            ...cm,
            phone: freshSite.phone || cm.phone,
            email: freshSite.email || cm.email,
            address: {
              street,
              city,
              zip: zip || cm.address.zip,
            },
            hours: {
              weekdays: hoursParts[0] || cm.hours.weekdays,
              saturday: hoursParts[1] || cm.hours.saturday,
              sunday: hoursParts[2] || cm.hours.sunday,
            },
            mapCoordinates: freshSite.mapCoordinates,
          };
        }

        // ── Reservation Settings ─────────────────────────────
        let freshReservation: ReservationSettings | null = null;
        if (reservationJson) {
          const rd = reservationJson.data || {};
          freshReservation = {
            formType: rd.form_type || 'internal',
            externalUrl: rd.external_url || null,
          };
        }

        // ── Navigation (update CTA from reservation settings) ─
        let freshNavigation: NavigationData | null = null;
        if (freshReservation) {
          const nav = {
            ...initialContent.navigation,
            links: [...initialContent.navigation.links],
          };
          if (
            freshReservation.formType === 'external' &&
            freshReservation.externalUrl
          ) {
            nav.cta = { ...nav.cta, href: freshReservation.externalUrl };
          } else if (freshReservation.formType === 'internal') {
            nav.cta = { ...nav.cta, href: '#rezerwacja' };
          }
          freshNavigation = nav;
        }

        // ── Motorcycles ──────────────────────────────────────
        let freshBikes: Motorcycle[] | null = null;
        let freshBikeCount = 0;
        if (bikesJson) {
          const mapped: Motorcycle[] = (bikesJson.data || []).map(mapApiMotorcycle);
          freshBikes = mapped;
          freshBikeCount = mapped.length;
        }

        // ── Apply all updates ────────────────────────────────
        setContent((prev) => ({
          ...prev,
          ...(freshSite && { site: freshSite }),
          ...(freshNavigation && { navigation: freshNavigation }),
          ...(freshHero && { hero: freshHero }),
          ...(freshWhyUs && { whyUs: freshWhyUs }),
          ...(freshHowItWorks && { howItWorks: freshHowItWorks }),
          ...(freshPricing && { pricing: freshPricing }),
          ...(freshTerms && { terms: freshTerms }),
          ...(freshGallery && { gallery: freshGallery }),
          ...(freshTestimonials && { testimonials: freshTestimonials }),
          ...(freshLocation && { location: freshLocation }),
          ...(freshContact && { contact: freshContact }),
          ...(freshReservation && { reservationSettings: freshReservation }),
        }));

        if (freshBikes !== null) {
          setBikes(freshBikes);
          setBikeCount(freshBikeCount!);
        }
      } catch (error) {
        console.error('DynamicContent: refresh failed, keeping initial data', error);
      }
    }

    refresh();

    return () => {
      cancelled = true;
    };
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

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
      <Gallery gallery={content.gallery} bikes={bikes} />
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
    </main>
  );
}
