/**
 * API Client - Pobieranie danych z Laravel API (api.example.test)
 *
 * Konfiguracja:
 * - NEXT_PUBLIC_API_URL - URL API (produkcja: https://api.example.test/api/motorent)
 * - NEXT_PUBLIC_API_DOMAIN - domena storage (zdjęcia, logo)
 * - NEXT_PUBLIC_TENANT_ID / TENANT_ID - ID tenanta demo-studio
 */

import mockContent from '@/data/mock-api-v2.json';

const LARAVEL_CMS_API = 'https://api.example.test/api/motorent';
const LARAVEL_CMS_DOMAIN = 'https://api.example.test';

// API Configuration (produkcja = Laravel CMS, lokalnie = localhost)
const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || LARAVEL_CMS_API;
const API_DOMAIN = process.env.NEXT_PUBLIC_API_DOMAIN || LARAVEL_CMS_DOMAIN;
const TENANT_ID = process.env.NEXT_PUBLIC_TENANT_ID || process.env.TENANT_ID || 'a0e1ef09-91b0-476a-aec1-45ae89c36bd4';

// Helper do budowania URL z tenant_id
function buildApiUrl(endpoint: string, params?: Record<string, string>): string {
  const url = new URL(`${API_BASE_URL}${endpoint}`);
  url.searchParams.set('tenant_id', TENANT_ID);
  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      url.searchParams.set(key, value);
    });
  }
  return url.toString();
}

/** Baza API (origin + /api) – rezerwacje: /api/v1/sites/... */
const API_BASE = (() => {
  try {
    const u = new URL(API_BASE_URL);
    return `${u.origin}/api`;
  } catch {
    return 'https://api.example.test/api';
  }
})();
const SITE_SLUG = 'motorent-demo';

export const RESERVATION_API_URL = `${API_BASE}/v1/sites/${SITE_SLUG}/plugins/reservations`;

export interface ReservationPayload {
  motorcycle_id?: string;
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  pickup_date: string;
  return_date: string;
  notes?: string;
  rodo_consent: boolean;
}

export interface ReservationResponse {
  success: boolean;
  message?: string;
  data?: { id: string; status: string; pickup_date: string; return_date: string };
}

/** Wysyła rezerwację do CMS (api.example.test). */
export async function submitReservation(payload: ReservationPayload): Promise<ReservationResponse> {
  const res = await fetch(RESERVATION_API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = (await res.json()) as ReservationResponse & { message?: string; errors?: Record<string, string[]> };
  if (!res.ok) {
    const msg = data.message || data.errors?.customer_email?.[0] || data.errors?.customer_phone?.[0] || `Błąd ${res.status}`;
    throw new Error(msg);
  }
  return data;
}

// Typy danych - Content (sekcje)
export interface SiteData {
  name: string;
  tagline: string;
  logo: string | null;
  description?: string;
  aboutUsContent?: string;
  regulaminContent?: string | null;
  politykaContent?: string | null;
  phone?: string;
  email?: string;
  address?: string;
  openingHours?: string;
  mapCoordinates?: string;
  googleAnalyticsCode?: string | null;
  locationTitle?: string | null;
  locationDescription?: string | null;
  companyData?: {
    company_name?: string;
    nip?: string;
    krs?: string;
    regon?: string;
  };
  socialMedia?: {
    facebook?: string;
    instagram?: string;
    tiktok?: string;
    linkedin?: string;
    youtube?: string;
  };
}

export interface ReservationSettings {
  formType: 'internal' | 'external';
  externalUrl?: string | null;
}

export interface NavLink {
  label: string;
  href: string;
}

export interface NavigationData {
  links: NavLink[];
  cta: NavLink & { variant?: string };
}

export interface HeroData {
  title: string;
  titleHighlight: string;
  subtitle: string;
  description?: string; // Z site-setting
  image: string;
  buttons: Array<NavLink & { variant: string }>;
  stats: Array<{ value: string; label: string }>;
}

export interface Feature {
  id: string;
  icon: string;
  title: string;
  description: string;
  order?: number;
}

export interface WhyUsData {
  title: string;
  subtitle: string;
  aboutUsContent?: string;
  features: Feature[];
}

export interface FleetData {
  title: string;
  subtitle: string;
  categories?: Array<{ label: string; value: string; active?: boolean }>;
}

export interface Step {
  number: number;
  title: string;
  description: string;
  icon?: string;
  published_at?: string; // Data publikacji z API
}

export interface HowItWorksData {
  title: string;
  subtitle: string;
  steps: Step[];
}

export interface PricingMotorcycle {
  id: string;
  name: string;
  slug: string;
  price_per_day: number;
  price_per_week: number;
  price_per_month: number;
  deposit: number;
}

export interface PricingNote {
  id: string;
  content: string;
}

export interface PricingData {
  title: string;
  subtitle: string;
  motorcycles?: PricingMotorcycle[];
  notes?: PricingNote[];
  table?: Array<{ period: string; price: string; deposit?: string }>;
  periods?: Array<{ label: string; key: string }>;
  extras?: Array<{ name: string; price: number; note?: string }>;
  disclaimer?: string;
}

export interface TermsData {
  title: string;
  items: Array<{
    title: string;
    icon?: string;
    description?: string;
    points: string[];
  }>;
}

export interface GalleryImage {
  id?: string;
  url: string;
  alt: string;
}

export interface GalleryData {
  title: string;
  subtitle: string;
  images?: GalleryImage[];
}

export interface Testimonial {
  id: string;
  name: string;
  rating: number;
  text: string;
  avatar?: string;
  motorcycle?: {
    id: string;
    name: string;
    slug: string;
  };
}

export interface TestimonialsData {
  title: string;
  subtitle: string;
  items: Testimonial[];
}

export interface LocationData {
  title: string;
  subtitle: string;
}

export interface ContactData {
  title: string;
  subtitle: string;
  address: {
    street: string;
    city: string;
    zip: string;
  };
  phone: string;
  email: string;
  hours: {
    weekdays: string;
    saturday: string;
    sunday: string;
  };
  mapCoordinates?: string;
  companyData?: {
    company_name?: string;
    nip?: string;
    krs?: string;
    regon?: string;
  };
  form: {
    namePlaceholder: string;
    emailPlaceholder: string;
    phonePlaceholder?: string;
    subjectPlaceholder?: string;
    messagePlaceholder: string;
    submitButton: string;
    consentText?: string;
  };
}

export interface FooterData {
  description: string;
  quickLinks?: {
    title: string;
    links: NavLink[];
  };
  infoLinks?: {
    title: string;
    links: NavLink[];
  };
  contactLinks?: {
    title: string;
    links: NavLink[];
  };
  socialMedia?: {
    facebook?: string;
    instagram?: string;
    tiktok?: string;
    linkedin?: string;
    youtube?: string;
  };
  menu?: {
    title: string;
    links: NavLink[];
  };
  legal: {
    copyright: string;
    creator?: string;
    links: NavLink[];
  };
}

// Typy danych - Motorcycles (z API)
export interface Brand {
  id: string;
  name: string;
  slug: string;
  logo?: string;
  description?: string;
}

export interface Category {
  id: string;
  name: string;
  slug: string;
  description?: string;
  color?: string;
}

export interface MotorcycleSpecs {
  engine: string;
  power: string;
  weight: string;
  seat_height?: string;
  seats?: number;
}

export interface MotorcycleImage {
  id?: string;
  url: string;
  alt: string;
}

export interface Motorcycle {
  id: string;
  name: string;
  slug: string;
  brand: Brand;
  category: Category;
  main_image?: MotorcycleImage;
  price_per_day: number;
  price_per_week?: number;
  price_per_month?: number;
  deposit: number;
  specs: MotorcycleSpecs;
  images: MotorcycleImage[];
  gallery?: MotorcycleImage[]; // Galeria zdjęć z API
  features: string[];
  available: boolean;
  featured?: boolean;
  year: number;
  engine_capacity?: number;
  description?: string;
  specifications?: {
    power?: string;
    seats?: number;
    engine?: string;
    weight?: string;
  };
}

export interface MotorcyclesMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number;
  to: number;
}

export interface MotorcyclesLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface MotorcyclesResponse {
  data: Motorcycle[];
  meta: MotorcyclesMeta;
  links: MotorcyclesLinks;
}

// Helper do konwersji ścieżek storage na pełne URL
function getStorageUrl(path: string | null | undefined): string {
  if (!path) return '/img/placeholder.jpg';
  if (path.startsWith('http')) return path;
  if (path.startsWith('/storage/')) return `${API_DOMAIN}${path}`;
  return path;
}

// Helper function to generate Google Maps embed URL from address or coordinates
export function generateMapUrl(
  address: { street: string; city: string; zip: string },
  coordinates?: string
): string {
  if (coordinates) {
    const [lat, lng] = coordinates.split(',').map(c => c.trim());
    return `https://maps.google.com/maps?q=${lat},${lng}&output=embed`;
  }
  const fullAddress = `${address.street}, ${address.zip} ${address.city}`;
  const encodedAddress = encodeURIComponent(fullAddress);
  return `https://maps.google.com/maps?q=${encodedAddress}&output=embed`;
}

// ============================================
// Funkcje API - Content (sekcje)
// ============================================

export async function getSiteData(): Promise<SiteData> {
  try {
    const response = await fetch(buildApiUrl('/site-setting'), {
      cache: 'no-store',
    });

    if (!response.ok) {
      // Podczas build time zwróć mock data zamiast rzucać błąd
      if (process.env.NODE_ENV === 'production' && process.env.NEXT_PHASE === 'phase-production-build') {
        console.warn('API unavailable during build, using mock data');
        return mockContent.content.site;
      }
      throw new Error(`API error: ${response.status}`);
    }

    const apiData = await response.json();
    const setting = apiData.data;

    // Parsuj adres
    const addressParts = setting.address?.split('\n') || [];
    const street = addressParts[0] || '';
    const cityZip = addressParts[1] || '';
    const [zip, ...cityParts] = cityZip.split(' ').reverse();
    const city = cityParts.reverse().join(' ');

    // Parsuj godziny otwarcia
    const hoursParts = setting.opening_hours?.split('\n') || [];
    const weekdays = hoursParts[0] || mockContent.content.sections.contact.hours.weekdays;
    const saturday = hoursParts[1] || mockContent.content.sections.contact.hours.saturday;

    return {
      name: setting.site_title || mockContent.content.site.name,
      tagline: mockContent.content.site.tagline,
      logo: getStorageUrl(setting.logo?.url || setting.logo), // Logo z API
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
      companyData: setting.company_data || undefined,
      socialMedia: setting.social_media || undefined,
    };
  } catch (error) {
    console.error('Error fetching site setting from API:', error);
    // Podczas build time zwróć mock data zamiast rzucać błąd
    if (process.env.NEXT_PHASE === 'phase-production-build' || process.env.NODE_ENV === 'production') {
      console.warn('API unavailable during build, using mock data');
      return mockContent.content.site;
    }
    throw error;
  }
}

export async function getNavigationData(): Promise<NavigationData> {
  const nav: NavigationData = { ...mockContent.content.navigation, links: [...mockContent.content.navigation.links], cta: { ...mockContent.content.navigation.cta } };
  // Replace LOGIN_ADMIN placeholder with actual admin URL
  nav.links = nav.links.map(link =>
    link.href === 'LOGIN_ADMIN'
      ? { ...link, href: `${API_DOMAIN}/admin` }
      : link
  );

  // Update CTA with reservation settings — always show "Rezerwuj" instead of login
  nav.cta.label = 'Rezerwuj';
  nav.cta.variant = 'primary';
  try {
    const resSetting = await getReservationSettings();
    if (resSetting.formType === 'external' && resSetting.externalUrl) {
      nav.cta.href = resSetting.externalUrl;
    } else {
      nav.cta.href = '#rezerwacja';
    }
  } catch {
    nav.cta.href = '#rezerwacja';
  }

  return nav;
}

export async function getHeroData(): Promise<HeroData> {
  try {
    const [siteData, heroMock] = await Promise.all([
      getSiteData(),
      Promise.resolve(mockContent.content.sections.hero),
    ]);
    
    return {
      ...heroMock,
      description: siteData.description, // Z site-setting
    };
  } catch (error) {
    console.error('Error fetching hero data:', error);
    // Podczas build time zwróć mock data zamiast rzucać błąd
    if (process.env.NEXT_PHASE === 'phase-production-build' || process.env.NODE_ENV === 'production') {
      console.warn('API unavailable during build, using mock data');
      return mockContent.content.sections.hero;
    }
    throw error;
  }
}

export async function getWhyUsData(): Promise<WhyUsData> {
  try {
    // Pobierz features i site settings równolegle
    const [featuresResponse, siteData] = await Promise.all([
      fetch(buildApiUrl('/features'), { cache: 'no-store' }),
      getSiteData(),
    ]);
    
    if (!featuresResponse.ok) {
      throw new Error(`API error: ${featuresResponse.status}`);
    }
    
    const apiData = await featuresResponse.json();
    const features: Feature[] = (apiData.data || [])
      .sort((a: any, b: any) => (a.order || 0) - (b.order || 0))
      .map((apiFeature: any) => ({
        id: apiFeature.id,
        title: apiFeature.title,
        description: apiFeature.description,
        icon: getStorageUrl(apiFeature.icon?.url),
        order: apiFeature.order,
      }));
    
    return {
      title: mockContent.content.sections.whyUs.title,
      subtitle: mockContent.content.sections.whyUs.subtitle,
      aboutUsContent: siteData.aboutUsContent || undefined,
      features,
    };
  } catch (error) {
    console.error('Error fetching features from API:', error);
    // Podczas build time zwróć mock data zamiast rzucać błąd
    if (process.env.NEXT_PHASE === 'phase-production-build' || process.env.NODE_ENV === 'production') {
      console.warn('API unavailable during build, using mock data');
      return mockContent.content.sections.whyUs as WhyUsData;
    }
    throw error;
  }
}

export async function getFleetData(): Promise<FleetData> {
  return mockContent.content.sections.fleet;
}

export async function getHowItWorksData(): Promise<HowItWorksData> {
  try {
    const response = await fetch(buildApiUrl('/process-steps'), {
      cache: 'no-store',
    });
    
    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }
    
    const apiData = await response.json();
    const now = new Date().toISOString();
    
    const steps: Step[] = (apiData.data || []).map((apiStep: any) => ({
      number: apiStep.step_number,
      title: apiStep.title,
      description: apiStep.description,
      icon: apiStep.icon_name,
      published_at: apiStep.published_at || now, // Domyślnie aktualna data
    }));
    
    const n = steps.length;
    const krokLabel = n === 1 ? 'w 1 kroku' : `w ${n} krokach`;
    return {
      title: mockContent.content.sections.howItWorks.title,
      subtitle: `Prosty proces rezerwacji ${krokLabel}`,
      steps,
    };
  } catch (error) {
    console.error('Error fetching process steps from API:', error);
    if (process.env.NEXT_PHASE === 'phase-production-build' || process.env.NODE_ENV === 'production') {
      console.warn('API unavailable during build, using mock data');
      const m = mockContent.content.sections.howItWorks as HowItWorksData;
      const n = m.steps?.length ?? 0;
      const krokLabel = n === 1 ? 'w 1 kroku' : `w ${n} krokach`;
      return { ...m, subtitle: `Prosty proces rezerwacji ${krokLabel}` };
    }
    throw error;
  }
}

export async function getPricingData(): Promise<PricingData> {
  try {
    const response = await fetch(buildApiUrl('/pricing'), {
      cache: 'no-store',
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }

    const apiData = await response.json();
    const data = apiData.data || {};

    return {
      title: data.title || mockContent.content.sections.pricing.title,
      subtitle: data.subtitle || mockContent.content.sections.pricing.subtitle,
      motorcycles: (data.motorcycles || []).map((m: any) => ({
        id: m.id,
        name: m.name,
        slug: m.slug,
        price_per_day: m.price_per_day,
        price_per_week: m.price_per_week,
        price_per_month: m.price_per_month,
        deposit: m.deposit,
      })),
      notes: (data.notes || []).map((n: any) => ({
        id: n.id,
        content: n.content,
      })),
    };
  } catch (error) {
    console.error('Error fetching pricing from API:', error);
    if (process.env.NEXT_PHASE === 'phase-production-build' || process.env.NODE_ENV === 'production') {
      console.warn('API unavailable during build, using mock data');
      return mockContent.content.sections.pricing;
    }
    throw error;
  }
}

export async function getTermsData(): Promise<TermsData> {
  try {
    const response = await fetch(buildApiUrl('/rental-conditions'), {
      cache: 'no-store',
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }

    const apiData = await response.json();
    const conditions = apiData.data || [];

    return {
      title: 'Warunki wypożyczenia',
      items: conditions.map((c: any) => ({
        title: c.title,
        icon: c.icon || undefined,
        description: c.description || undefined,
        points: [],
      })),
    };
  } catch (error) {
    console.error('Error fetching rental conditions from API:', error);
    if (process.env.NEXT_PHASE === 'phase-production-build' || process.env.NODE_ENV === 'production') {
      console.warn('API unavailable during build, using mock data');
      return mockContent.content.sections.terms;
    }
    throw error;
  }
}

export async function getGalleryData(): Promise<GalleryData> {
  try {
    const response = await fetch(buildApiUrl('/gallery'), {
      cache: 'no-store',
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }

    const apiData = await response.json();
    const data = apiData.data || {};

    return {
      title: data.title || mockContent.content.sections.gallery.title,
      subtitle: data.subtitle || mockContent.content.sections.gallery.subtitle,
      images: (data.images || []).map((img: { id?: string; url: string; alt: string }) => ({
        id: img.id,
        url: getStorageUrl(img.url),
        alt: img.alt || '',
      })),
    };
  } catch (error) {
    console.error('Error fetching gallery from API:', error);
    return mockContent.content.sections.gallery;
  }
}

export async function getTestimonialsData(): Promise<TestimonialsData> {
  try {
    const response = await fetch(buildApiUrl('/testimonials'), {
      cache: 'no-store',
    });
    
    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }
    
    const apiData = await response.json();
    const testimonials: Testimonial[] = (apiData.data || []).map((apiTestimonial: any) => ({
      id: apiTestimonial.id,
      name: apiTestimonial.author_name,
      rating: apiTestimonial.rating,
      text: apiTestimonial.content,
      motorcycle: apiTestimonial.motorcycle,
    }));
    
    return {
      title: mockContent.content.sections.testimonials.title,
      subtitle: mockContent.content.sections.testimonials.subtitle,
      items: testimonials,
    };
  } catch (error) {
    console.error('Error fetching testimonials from API:', error);
    // Podczas build time zwróć mock data zamiast rzucać błąd
    if (process.env.NEXT_PHASE === 'phase-production-build' || process.env.NODE_ENV === 'production') {
      console.warn('API unavailable during build, using mock data');
      return mockContent.content.sections.testimonials as TestimonialsData;
    }
    throw error;
  }
}

export async function getLocationData(): Promise<LocationData> {
  try {
    const siteData = await getSiteData();
    return {
      title: siteData.locationTitle || mockContent.content.sections.location.title,
      subtitle: siteData.locationDescription || mockContent.content.sections.location.subtitle,
    };
  } catch (error) {
    console.error('Error fetching location data:', error);
    return mockContent.content.sections.location;
  }
}

export async function getReservationSettings(): Promise<ReservationSettings> {
  try {
    const response = await fetch(buildApiUrl('/reservation-settings'), {
      cache: 'no-store',
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }

    const apiData = await response.json();
    const data = apiData.data || {};

    return {
      formType: data.form_type || 'internal',
      externalUrl: data.external_url || null,
    };
  } catch (error) {
    console.error('Error fetching reservation settings:', error);
    return { formType: 'external', externalUrl: null };
  }
}

export async function getContactData(): Promise<ContactData> {
  try {
    const siteData = await getSiteData();
    const contactMock = mockContent.content.sections.contact;
    
    // Parsuj adres z API
    const addressParts = siteData.address?.split('\n') || [];
    const street = addressParts[0] || contactMock.address.street;
    const cityZip = addressParts[1] || `${contactMock.address.zip} ${contactMock.address.city}`;
    const [zip, ...cityParts] = cityZip.split(' ').reverse();
    const city = cityParts.reverse().join(' ') || contactMock.address.city;
    
    // Parsuj godziny otwarcia
    const hoursParts = siteData.openingHours?.split('\n').filter(Boolean) || [];
    const weekdays = hoursParts[0] || contactMock.hours.weekdays;
    const saturday = hoursParts[1] || contactMock.hours.saturday;
    const sunday = hoursParts[2] || contactMock.hours.sunday;

    return {
      ...contactMock,
      phone: siteData.phone || contactMock.phone,
      email: siteData.email || contactMock.email,
      address: {
        street,
        city,
        zip: zip || contactMock.address.zip,
      },
      hours: {
        weekdays,
        saturday,
        sunday,
      },
      mapCoordinates: siteData.mapCoordinates,
      companyData: siteData.companyData,
    };
  } catch (error) {
    console.error('Error fetching contact data:', error);
    // Podczas build time zwróć mock data zamiast rzucać błąd
    if (process.env.NEXT_PHASE === 'phase-production-build' || process.env.NODE_ENV === 'production') {
      console.warn('API unavailable during build, using mock data');
      return mockContent.content.sections.contact as ContactData;
    }
    throw error;
  }
}

export async function getFooterData(): Promise<FooterData> {
  const footer: FooterData = { ...mockContent.content.footer };
  // Merge social_media from API (site-setting) if available
  try {
    const siteData = await getSiteData();
    if (siteData.socialMedia && Object.values(siteData.socialMedia).some(Boolean)) {
      footer.socialMedia = siteData.socialMedia;
    }
  } catch {
    // Keep mock social links on error
  }
  return footer;
}

// Funkcja uniwersalna - pobiera wszystkie dane content naraz (zoptymalizowana)
export async function getAllContent() {
  // Fetch siteData once and all independent endpoints in parallel
  const [
    siteData,
    navigation,
    fleet,
    howItWorks,
    pricing,
    terms,
    gallery,
    testimonials,
    footer,
    reservationSettings,
  ] = await Promise.all([
    getSiteData(),
    getNavigationData(),
    getFleetData(),
    getHowItWorksData(),
    getPricingData(),
    getTermsData(),
    getGalleryData(),
    getTestimonialsData(),
    getFooterData(),
    getReservationSettings(),
  ]);

  // Build dependent data from siteData (no extra API calls)
  const hero: HeroData = {
    ...mockContent.content.sections.hero,
    description: siteData.description,
  };

  // Features still needs its own API call, but we use the already-fetched siteData
  let whyUs: WhyUsData;
  try {
    const featuresResponse = await fetch(buildApiUrl('/features'), { cache: 'no-store' });
    if (!featuresResponse.ok) throw new Error(`API error: ${featuresResponse.status}`);
    const apiData = await featuresResponse.json();
    const features: Feature[] = (apiData.data || [])
      .sort((a: any, b: any) => (a.order || 0) - (b.order || 0))
      .map((apiFeature: any) => ({
        id: apiFeature.id,
        title: apiFeature.title,
        description: apiFeature.description,
        icon: getStorageUrl(apiFeature.icon?.url),
        order: apiFeature.order,
      }));
    whyUs = {
      title: mockContent.content.sections.whyUs.title,
      subtitle: mockContent.content.sections.whyUs.subtitle,
      aboutUsContent: siteData.aboutUsContent || undefined,
      features,
    };
  } catch {
    whyUs = {
      ...mockContent.content.sections.whyUs as WhyUsData,
      aboutUsContent: siteData.aboutUsContent || undefined,
    };
  }

  const location: LocationData = {
    title: siteData.locationTitle || mockContent.content.sections.location.title,
    subtitle: siteData.locationDescription || mockContent.content.sections.location.subtitle,
  };

  // Build contact from siteData (no extra API call)
  const contactMock = mockContent.content.sections.contact;
  const addressParts = siteData.address?.split('\n') || [];
  const street = addressParts[0] || contactMock.address.street;
  const cityZip = addressParts[1] || `${contactMock.address.zip} ${contactMock.address.city}`;
  const [zip, ...cityParts] = cityZip.split(' ').reverse();
  const city = cityParts.reverse().join(' ') || contactMock.address.city;
  const hoursParts = siteData.openingHours?.split('\n').filter(Boolean) || [];
  const contact: ContactData = {
    ...contactMock,
    phone: siteData.phone || contactMock.phone,
    email: siteData.email || contactMock.email,
    address: { street, city, zip: zip || contactMock.address.zip },
    hours: {
      weekdays: hoursParts[0] || contactMock.hours.weekdays,
      saturday: hoursParts[1] || contactMock.hours.saturday,
      sunday: hoursParts[2] || contactMock.hours.sunday,
    },
    mapCoordinates: siteData.mapCoordinates,
  };

  return {
    site: siteData,
    navigation,
    hero,
    whyUs,
    fleet,
    howItWorks,
    pricing,
    terms,
    gallery,
    testimonials,
    location,
    contact,
    footer,
    reservationSettings,
  };
}

// ============================================
// Funkcje API - Kolekcje (z prawdziwego API)
// ============================================

/**
 * Pobiera marki z API
 */
export async function getBrands(): Promise<Brand[]> {
  const response = await fetch(buildApiUrl('/brands'), {
    cache: 'no-store', // Dynamic - fresh data on every request
  });
  
  if (!response.ok) {
    throw new Error(`API error: ${response.status}`);
  }
  
  const data = await response.json();
  return data.data || [];
}

/**
 * Pobiera kategorie z API
 */
export async function getCategories(): Promise<Category[]> {
  const response = await fetch(buildApiUrl('/categories'), {
    cache: 'no-store',
  });
  
  if (!response.ok) {
    throw new Error(`API error: ${response.status}`);
  }
  
  const data = await response.json();
  return data.data || [];
}

/**
 * Mapuje odpowiedź API na nasz format Motorcycle
 */
function mapApiMotorcycle(apiMoto: any): Motorcycle {
  // Napraw ścieżki obrazków
  const mainImage = apiMoto.main_image ? {
    ...apiMoto.main_image,
    url: getStorageUrl(apiMoto.main_image.url),
  } : undefined;
  
  // Mapuj galerię zdjęć
  const galleryImages: MotorcycleImage[] = (apiMoto.gallery || []).map((img: any) => ({
    id: img.id,
    url: getStorageUrl(img.url),
    alt: img.alt_text || img.alt || apiMoto.name,
  }));
  
  // Wszystkie obrazy: main_image + gallery
  const allImages = mainImage 
    ? [mainImage, ...galleryImages]
    : galleryImages;
  
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

/**
 * Pobiera motocykle z API
 */
export interface GetMotorcyclesParams {
  slug?: string;
  category?: string;
  brand?: string;
  available?: boolean;
  min_price?: number;
  max_price?: number;
  per_page?: number;
  page?: number;
  sort?: 'price_asc' | 'price_desc' | 'name_asc' | 'name_desc';
}

export async function getMotorcycles(params: GetMotorcyclesParams = {}): Promise<MotorcyclesResponse> {
  try {
    const queryParams: Record<string, string> = {};
    
    if (params.category && params.category !== 'wszystkie') {
      queryParams.category = params.category;
    }
    if (params.brand) {
      queryParams.brand = params.brand;
    }
    if (params.per_page) {
      queryParams.per_page = params.per_page.toString();
    }
    if (params.page) {
      queryParams.page = params.page.toString();
    }
    
    const response = await fetch(buildApiUrl('/motorcycles', queryParams), {
      cache: 'no-store', // Dynamic - fresh data on every request
    });
    
    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }
    
    const apiData = await response.json();
    const motorcycles = (apiData.data || []).map(mapApiMotorcycle);
    
    // Filtrowanie po stronie klienta (jeśli API nie obsługuje)
    let filteredBikes: Motorcycle[] = motorcycles;
    
    if (params.available !== undefined) {
      filteredBikes = filteredBikes.filter((bike: Motorcycle) => bike.available === params.available);
    }
    if (params.min_price !== undefined) {
      filteredBikes = filteredBikes.filter((bike: Motorcycle) => bike.price_per_day >= params.min_price!);
    }
    if (params.max_price !== undefined) {
      filteredBikes = filteredBikes.filter((bike: Motorcycle) => bike.price_per_day <= params.max_price!);
    }
    
    // Sortowanie
    if (params.sort) {
      switch (params.sort) {
        case 'price_asc':
          filteredBikes.sort((a: Motorcycle, b: Motorcycle) => a.price_per_day - b.price_per_day);
          break;
        case 'price_desc':
          filteredBikes.sort((a: Motorcycle, b: Motorcycle) => b.price_per_day - a.price_per_day);
          break;
        case 'name_asc':
          filteredBikes.sort((a: Motorcycle, b: Motorcycle) => a.name.localeCompare(b.name));
          break;
        case 'name_desc':
          filteredBikes.sort((a: Motorcycle, b: Motorcycle) => b.name.localeCompare(a.name));
          break;
      }
    }
    
    const total = filteredBikes.length;
    const perPage = params.per_page || 20;
    const page = params.page || 1;
    
    return {
      data: filteredBikes,
      meta: {
        current_page: page,
        per_page: perPage,
        total,
        last_page: Math.ceil(total / perPage),
        from: (page - 1) * perPage + 1,
        to: Math.min(page * perPage, total),
      },
      links: {
        first: null,
        last: null,
        prev: null,
        next: null,
      },
    };
  } catch (error) {
    console.error('Error fetching motorcycles from API:', error);
    // Podczas build time zwróć pustą listę zamiast rzucać błąd
    if (process.env.NEXT_PHASE === 'phase-production-build' || process.env.NODE_ENV === 'production') {
      console.warn('API unavailable during build, using empty motorcycles list');
      return {
        data: [],
        meta: {
          current_page: 1,
          per_page: 0,
          total: 0,
          last_page: 1,
          from: 0,
          to: 0,
        },
        links: {
          first: null,
          last: null,
          prev: null,
          next: null,
        },
      };
    }
    throw error;
  }
}

/**
 * Pobiera pojedynczy motocykl po slug
 */
export async function getMotorcycleBySlug(slug: string): Promise<Motorcycle | null> {
  try {
    const response = await fetch(buildApiUrl(`/motorcycles/${slug}`), {
      cache: 'no-store',
    });
    
    if (!response.ok) {
      return null; // 404, 500, API niedostępne – strona wywoła notFound()
    }
    
    const apiData = await response.json();
    return mapApiMotorcycle(apiData.data || apiData);
  } catch (error) {
    console.error('Error fetching motorcycle by slug:', error);
    return null;
  }
}

/**
 * Pobiera wyróżnione motocykle
 */
export async function getFeaturedMotorcycles(): Promise<Motorcycle[]> {
  const result = await getMotorcycles({ per_page: 50 });
  return result.data.filter((m: Motorcycle) => m.featured);
}
