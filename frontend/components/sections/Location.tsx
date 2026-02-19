import { generateMapUrl } from '@/lib/api';
import type { LocationData, ContactData } from '@/lib/api';

interface LocationProps {
  location: LocationData;
  contact: ContactData;
}

export default function Location({ location, contact }: LocationProps) {
  const mapUrl = generateMapUrl(contact.address, contact.mapCoordinates);

  return (
    <section id="kontakt" className="py-20 bg-white">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
            {location.title}
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
            <div>
              <h3 className="font-heading text-xl font-bold mb-2">Adres</h3>
              <p className="text-gray-medium">
                {contact.address.street}<br />
                {contact.address.zip} {contact.address.city}
              </p>
            </div>

            <div>
              <h3 className="font-heading text-xl font-bold mb-2">Kontakt</h3>
              <p className="text-gray-medium">
                Telefon: <a href={`tel:${contact.phone.replace(/\s/g, '')}`} className="text-accent-red hover:underline">{contact.phone}</a>
                <br />
                Email: <a href={`mailto:${contact.email}`} className="text-accent-red hover:underline">{contact.email}</a>
              </p>
            </div>

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
