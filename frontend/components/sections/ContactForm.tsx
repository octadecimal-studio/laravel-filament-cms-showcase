import { MONDAY_RESERVATION_FORM_URL } from '@/lib/paths';
import type { ContactData, ReservationSettings } from '@/lib/api';

interface ContactFormProps {
  contact: ContactData;
  reservationSettings?: ReservationSettings;
}

export default function ContactForm({ contact, reservationSettings }: ContactFormProps) {
  const formType = reservationSettings?.formType || 'external';
  const externalUrl = reservationSettings?.externalUrl || MONDAY_RESERVATION_FORM_URL;

  return (
    <section id="rezerwacja" className="py-20 bg-gray-light">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
            {contact.title}
          </h2>
          <p className="text-lg text-gray-medium max-w-2xl mx-auto">
            {contact.subtitle}
          </p>
        </div>
        <div className="max-w-2xl mx-auto text-center">
          {formType === 'external' && externalUrl ? (
            <a
              href={externalUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-block bg-accent-red text-white px-10 py-4 rounded-lg font-semibold text-lg hover:bg-red-700 transition-colors"
            >
              Rezerwuj teraz
            </a>
          ) : (
            <a
              href="#kontakt"
              className="inline-block bg-accent-red text-white px-10 py-4 rounded-lg font-semibold text-lg hover:bg-red-700 transition-colors"
            >
              Rezerwuj teraz
            </a>
          )}
        </div>
      </div>
    </section>
  );
}
