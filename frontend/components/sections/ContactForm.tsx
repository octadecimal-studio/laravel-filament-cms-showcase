'use client';

import { useState } from 'react';
import { MONDAY_RESERVATION_FORM_URL } from '@/lib/paths';
import type { ContactData, ReservationSettings } from '@/lib/api';

interface ContactFormProps {
  contact: ContactData;
  reservationSettings?: ReservationSettings;
}

export default function ContactForm({ contact, reservationSettings }: ContactFormProps) {
  const formType = reservationSettings?.formType || 'external';
  const externalUrl = reservationSettings?.externalUrl || MONDAY_RESERVATION_FORM_URL;

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [message, setMessage] = useState('');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const subject = encodeURIComponent(`Zapytanie od ${name}`);
    const body = encodeURIComponent(
      `Imię i nazwisko: ${name}\nEmail: ${email}\nTelefon: ${phone}\n\n${message}`
    );
    window.location.href = `mailto:${contact.email}?subject=${subject}&body=${body}`;
  };

  return (
    <section id="kontakt" className="py-20 bg-gray-light">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
            {contact.title}
          </h2>
          <p className="text-lg text-gray-medium max-w-2xl mx-auto">
            {contact.subtitle}
          </p>
        </div>

        <div className="max-w-4xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12">
          {/* Contact Form */}
          <div className="bg-white rounded-xl shadow-lg p-8">
            <h3 className="font-heading text-2xl font-bold mb-6">Napisz do nas</h3>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <input
                  type="text"
                  required
                  placeholder={contact.form.namePlaceholder}
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-red focus:border-transparent outline-none transition-all"
                />
              </div>
              <div>
                <input
                  type="email"
                  required
                  placeholder={contact.form.emailPlaceholder}
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-red focus:border-transparent outline-none transition-all"
                />
              </div>
              {contact.form.phonePlaceholder && (
                <div>
                  <input
                    type="tel"
                    placeholder={contact.form.phonePlaceholder}
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-red focus:border-transparent outline-none transition-all"
                  />
                </div>
              )}
              <div>
                <textarea
                  required
                  placeholder={contact.form.messagePlaceholder}
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                  rows={5}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-red focus:border-transparent outline-none transition-all resize-none"
                />
              </div>
              {contact.form.consentText && (
                <p className="text-xs text-gray-medium">{contact.form.consentText}</p>
              )}
              <button
                type="submit"
                className="w-full bg-accent-red text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-red-700 transition-colors"
              >
                {contact.form.submitButton}
              </button>
            </form>
          </div>

          {/* Contact Info + Reservation CTA */}
          <div className="flex flex-col gap-6">
            <div className="bg-white rounded-xl shadow-lg p-8">
              <h3 className="font-heading text-2xl font-bold mb-6">Dane kontaktowe</h3>
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-gray-medium mb-1">Adres</p>
                  <p className="font-semibold">{contact.address.street}</p>
                  <p className="font-semibold">{contact.address.zip} {contact.address.city}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-medium mb-1">Telefon</p>
                  <a href={`tel:${contact.phone}`} className="font-semibold text-accent-red hover:underline">
                    {contact.phone}
                  </a>
                </div>
                <div>
                  <p className="text-sm text-gray-medium mb-1">Email</p>
                  <a href={`mailto:${contact.email}`} className="font-semibold text-accent-red hover:underline">
                    {contact.email}
                  </a>
                </div>
                <div>
                  <p className="text-sm text-gray-medium mb-1">Godziny otwarcia</p>
                  <p>{contact.hours.weekdays}</p>
                  <p>{contact.hours.saturday}</p>
                  <p>{contact.hours.sunday}</p>
                </div>
              </div>
            </div>

            {/* Reservation CTA */}
            <div id="rezerwacja" className="bg-primary-black rounded-xl shadow-lg p-8 text-center">
              <h3 className="font-heading text-2xl font-bold mb-4 text-white">Zarezerwuj motocykl</h3>
              <p className="text-gray-400 mb-6">Kliknij poniżej, aby wypełnić formularz rezerwacji</p>
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
                  href={`tel:${contact.phone}`}
                  className="inline-block bg-accent-red text-white px-10 py-4 rounded-lg font-semibold text-lg hover:bg-red-700 transition-colors"
                >
                  Zadzwoń i zarezerwuj
                </a>
              )}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
