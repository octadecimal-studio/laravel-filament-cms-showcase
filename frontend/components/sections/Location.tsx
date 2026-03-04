import { generateMapUrl } from '@/lib/api';
import type { LocationData, ContactData } from '@/lib/api';

interface LocationProps {
  location: LocationData;
  contact: ContactData;
}

export default function Location({ location, contact }: LocationProps) {
  const mapUrl = generateMapUrl(contact.address, contact.mapCoordinates);
  const company = contact.companyData;

  return (
    <section id="kontakt" className="py-20 bg-white">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
            Dane kontaktowe
          </h2>
          <p className="text-lg text-gray-medium max-w-2xl mx-auto">
            {location.subtitle}
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">
          {/* Mapa */}
          <div className="bg-gray-light rounded-xl overflow-hidden h-96">
            <iframe
              src={mapUrl}
              width="100%"
              height="100%"
              style={{ border: 0 }}
              allowFullScreen
              loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
              className="w-full h-full"
            />
          </div>

          {/* Informacje */}
          <div className="space-y-6">
            {/* Adres */}
            <div>
              <h3 className="font-heading text-xl font-bold mb-2">Adres</h3>
              <p className="text-gray-medium">
                {company?.company_name && <>{company.company_name}<br /></>}
                {contact.address.street}<br />
                {contact.address.zip} {contact.address.city}
              </p>
              {(company?.nip || company?.krs || company?.regon) && (
                <p className="text-gray-medium mt-2">
                  {company.nip && <>NIP: {company.nip}<br /></>}
                  {company.krs && <>KRS: {company.krs}<br /></>}
                  {company.regon && <>REGON: {company.regon}</>}
                </p>
              )}
            </div>

            {/* Kontakt */}
            <div>
              <h3 className="font-heading text-xl font-bold mb-2">Kontakt</h3>
              <p className="text-gray-medium">
                Telefon: <a href={`tel:${contact.phone.replace(/\s/g, '')}`} className="text-accent-red hover:underline">{contact.phone}</a>
                <br />
                Email: <a href={`mailto:${contact.email}`} className="text-accent-red hover:underline">{contact.email}</a>
              </p>
            </div>

            {/* Godziny otwarcia */}
            <div>
              <h3 className="font-heading text-xl font-bold mb-2">Godziny otwarcia</h3>
              <p className="text-gray-medium">
                {contact.hours.weekdays}<br />
                {contact.hours.saturday}<br />
                {contact.hours.sunday}
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
