'use client';

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
import type {
  SiteData, NavigationData, HeroData, WhyUsData, FleetData,
  HowItWorksData, PricingData, TermsData, GalleryData,
  TestimonialsData, LocationData, ContactData, FooterData,
  ReservationSettings, Motorcycle,
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

export default function DynamicContent({
  initialContent,
  initialBikes,
  totalBikes,
}: DynamicContentProps) {
  const content = initialContent;
  const bikes = initialBikes;
  const bikeCount = totalBikes;

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
