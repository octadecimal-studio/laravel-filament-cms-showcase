import type { Metadata } from 'next';
import Link from 'next/link';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import { getAllContent } from '@/lib/api';

export const dynamic = 'force-dynamic';
export const revalidate = 0;

export const metadata: Metadata = {
  title: 'Płatność nieudana | MotoRent',
  description: 'Wystąpił problem z płatnością.',
  robots: { index: false, follow: false },
};

type Props = {
  searchParams: Promise<{
    rental?: string;
    reason?: string;
    code?: string;
  }>;
};

const REASON_MESSAGES: Record<string, string> = {
  cancelled: 'Płatność została anulowana.',
  declined: 'Bank odrzucił płatność.',
  timeout: 'Sesja płatności wygasła.',
  failed: 'Płatność się nie powiodła.',
};

/**
 * Strona bledu platnosci.
 *
 * Query params:
 *  - rental: UUID rezerwacji (rezerwacja zostala utworzona, ale nie oplacona)
 *  - reason: cancelled | declined | timeout | failed
 *  - code: opcjonalny kod bledu od P24
 *
 * Rezerwacja w stanie 'pending' wygasnie automatycznie wedlug
 * RESERVATION_PENDING_EXPIRY_MIN (config rental.pending_expiry_min, default 30 min).
 *
 * @see KML-0063 (G3)
 */
export default async function ReservationFailurePage({ searchParams }: Props) {
  const params = await searchParams;
  const rentalRef = params.rental ? params.rental.slice(0, 8).toUpperCase() : null;
  const reason = params.reason && REASON_MESSAGES[params.reason]
    ? REASON_MESSAGES[params.reason]
    : 'Wystąpił problem z płatnością.';

  const content = await getAllContent();

  return (
    <main className="min-h-screen bg-gray-light">
      <Header site={content.site} navigation={content.navigation} />

      <section className="py-20 pt-32">
        <div className="container mx-auto px-4">
          <div className="max-w-2xl mx-auto bg-white rounded-2xl shadow-lg p-8 md:p-12 text-center">
            <div className="inline-flex items-center justify-center w-20 h-20 bg-red-100 rounded-full mb-6">
              <svg
                className="w-10 h-10 text-red-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </div>

            <h1 className="font-heading text-3xl md:text-4xl font-bold mb-4">
              Płatność nieudana
            </h1>

            <p className="text-gray-medium mb-6">{reason}</p>

            {rentalRef && (
              <div className="bg-gray-50 rounded-lg p-4 mb-6 inline-block">
                <div className="text-xs text-gray-500 mb-1">Numer rezerwacji</div>
                <div className="font-mono font-bold text-xl text-primary-black">
                  {rentalRef}
                </div>
                {params.code && (
                  <div className="text-xs text-gray-400 mt-2">
                    Kod błędu: {params.code}
                  </div>
                )}
              </div>
            )}

            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-8 text-sm text-yellow-800 text-left">
              <p className="font-semibold mb-2">Co możesz zrobić?</p>
              <ul className="space-y-1 list-disc list-inside">
                <li>Spróbuj ponownie złożyć rezerwację z innym sposobem płatności.</li>
                <li>
                  Zadzwoń do nas:{' '}
                  <a
                    href={`tel:${content.contact.phone}`}
                    className="text-accent-red font-semibold hover:underline"
                  >
                    {content.contact.phone}
                  </a>{' '}
                  — pomożemy dokończyć rezerwację.
                </li>
                <li>
                  Napisz na email:{' '}
                  <a
                    href={`mailto:${content.contact.email}`}
                    className="text-accent-red font-semibold hover:underline"
                  >
                    {content.contact.email}
                  </a>
                </li>
              </ul>
            </div>

            <div className="flex flex-col sm:flex-row gap-3 justify-center">
              <Link
                href="/#motocykle"
                className="bg-accent-red text-white px-8 py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors"
              >
                Spróbuj ponownie
              </Link>
              <Link
                href="/"
                className="bg-gray-100 text-gray-700 px-8 py-3 rounded-lg font-semibold hover:bg-gray-200 transition-colors"
              >
                Powrót na stronę główną
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
