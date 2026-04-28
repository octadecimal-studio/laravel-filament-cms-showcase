import type { Metadata } from 'next';
import Link from 'next/link';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import { getAllContent } from '@/lib/api';

export const dynamic = 'force-dynamic';
export const revalidate = 0;

export const metadata: Metadata = {
  title: 'Dziękujemy za rezerwację | MotoRent',
  description: 'Twoja rezerwacja została przyjęta.',
  robots: { index: false, follow: false },
};

type Props = {
  searchParams: Promise<{
    rental?: string;
    status?: string;
  }>;
};

/**
 * Strona sukcesu — return URL z Przelewy24 po udanej platnosci.
 *
 * Query params:
 *  - rental: UUID rezerwacji
 *  - status: opcjonalny status z P24 (success, completed)
 *
 * Webhook od P24 (POST /api/payment/webhook) potwierdza platnosc serwerowo
 * i wysyla email — ta strona jest tylko dla usera.
 *
 * @see KML-0063 (G3)
 */
export default async function ReservationSuccessPage({ searchParams }: Props) {
  const params = await searchParams;
  const rentalRef = params.rental ? params.rental.slice(0, 8).toUpperCase() : null;

  const content = await getAllContent();

  return (
    <main className="min-h-screen bg-gray-light">
      <Header site={content.site} navigation={content.navigation} />

      <section className="py-20 pt-32">
        <div className="container mx-auto px-4">
          <div className="max-w-2xl mx-auto bg-white rounded-2xl shadow-lg p-8 md:p-12 text-center">
            <div className="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-6">
              <svg
                className="w-10 h-10 text-green-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M5 13l4 4L19 7"
                />
              </svg>
            </div>

            <h1 className="font-heading text-3xl md:text-4xl font-bold mb-4">
              Dziękujemy za rezerwację!
            </h1>

            <p className="text-gray-medium mb-6">
              Twoja płatność została przyjęta. W ciągu kilku minut otrzymasz email
              z potwierdzeniem rezerwacji.
            </p>

            {rentalRef && (
              <div className="bg-gray-50 rounded-lg p-4 mb-6 inline-block">
                <div className="text-xs text-gray-500 mb-1">Numer rezerwacji</div>
                <div className="font-mono font-bold text-xl text-primary-black">
                  {rentalRef}
                </div>
              </div>
            )}

            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8 text-sm text-blue-800 text-left">
              <p className="font-semibold mb-2">Co dalej?</p>
              <ul className="space-y-1 list-disc list-inside">
                <li>Skontaktujemy się z Tobą w celu ustalenia szczegółów odbioru.</li>
                <li>Sprawdź email (również folder spam) — wyślemy potwierdzenie.</li>
                <li>
                  W razie pytań zadzwoń do nas:{' '}
                  <a
                    href={`tel:${content.contact.phone}`}
                    className="text-accent-red font-semibold hover:underline"
                  >
                    {content.contact.phone}
                  </a>
                </li>
              </ul>
            </div>

            <div className="flex flex-col sm:flex-row gap-3 justify-center">
              <Link
                href="/"
                className="bg-accent-red text-white px-8 py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors"
              >
                Powrót na stronę główną
              </Link>
              <Link
                href="/#motocykle"
                className="bg-gray-100 text-gray-700 px-8 py-3 rounded-lg font-semibold hover:bg-gray-200 transition-colors"
              >
                Zobacz inne motocykle
              </Link>
            </div>
          </div>
        </div>
      </section>

      <Footer
        site={content.site}
        footer={content.footer}
        contact={content.contact}
      />
    </main>
  );
}
