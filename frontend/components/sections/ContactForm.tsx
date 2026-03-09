'use client';

import { useState } from 'react';
import { MONDAY_RESERVATION_FORM_URL } from '@/lib/paths';
import { submitReservation } from '@/lib/api';
import type { ContactData, ReservationSettings, Motorcycle } from '@/lib/api';

interface ContactFormProps {
  contact: ContactData;
  reservationSettings?: ReservationSettings;
  bikes?: Motorcycle[];
}

export default function ContactForm({ contact, reservationSettings, bikes }: ContactFormProps) {
  const formType = reservationSettings?.formType || 'external';
  const externalUrl = reservationSettings?.externalUrl || MONDAY_RESERVATION_FORM_URL;

  // Formularz kontaktowy (mailto)
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

  // Formularz rezerwacji (wewnętrzny)
  const [resName, setResName] = useState('');
  const [resEmail, setResEmail] = useState('');
  const [resPhone, setResPhone] = useState('');
  const [pickupDate, setPickupDate] = useState('');
  const [returnDate, setReturnDate] = useState('');
  const [motorcycleId, setMotorcycleId] = useState('');
  const [notes, setNotes] = useState('');
  const [rodoConsent, setRodoConsent] = useState(false);
  const [resStatus, setResStatus] = useState<'idle' | 'sending' | 'success' | 'error'>('idle');
  const [resError, setResError] = useState('');

  const handleReservation = async (e: React.FormEvent) => {
    e.preventDefault();
    setResStatus('sending');
    setResError('');

    try {
      await submitReservation({
        customer_name: resName,
        customer_email: resEmail,
        customer_phone: resPhone,
        pickup_date: pickupDate,
        return_date: returnDate,
        motorcycle_id: motorcycleId || undefined,
        notes: notes || undefined,
        rodo_consent: rodoConsent,
      });
      setResStatus('success');
      setResName('');
      setResEmail('');
      setResPhone('');
      setPickupDate('');
      setReturnDate('');
      setMotorcycleId('');
      setNotes('');
      setRodoConsent(false);
    } catch (err) {
      setResStatus('error');
      setResError(err instanceof Error ? err.message : 'Wystąpił błąd. Spróbuj ponownie.');
    }
  };

  // Minimalna data — jutro
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const minDate = tomorrow.toISOString().split('T')[0];

  const inputClass = "w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-red focus:border-transparent outline-none transition-all";

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
                  className={inputClass}
                />
              </div>
              <div>
                <input
                  type="email"
                  required
                  placeholder={contact.form.emailPlaceholder}
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className={inputClass}
                />
              </div>
              {contact.form.phonePlaceholder && (
                <div>
                  <input
                    type="tel"
                    placeholder={contact.form.phonePlaceholder}
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    className={inputClass}
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
                  className={`${inputClass} resize-none`}
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
              {formType === 'external' && externalUrl ? (
                <>
                  <p className="text-gray-400 mb-6">Kliknij poniżej, aby wypełnić formularz rezerwacji</p>
                  <a
                    href={externalUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-block bg-accent-red text-white px-10 py-4 rounded-lg font-semibold text-lg hover:bg-red-700 transition-colors"
                  >
                    Rezerwuj teraz
                  </a>
                </>
              ) : (
                <>
                  <p className="text-gray-400 mb-6">Wypełnij formularz, a skontaktujemy się z&nbsp;Tobą</p>

                  {resStatus === 'success' ? (
                    <div className="text-left bg-green-900/30 border border-green-500/50 rounded-lg p-6">
                      <p className="text-green-400 font-semibold mb-2">Rezerwacja wysłana!</p>
                      <p className="text-gray-300 text-sm">Dziękujemy za zgłoszenie. Skontaktujemy się z Tobą w celu potwierdzenia rezerwacji.</p>
                      <button
                        type="button"
                        onClick={() => setResStatus('idle')}
                        className="mt-4 text-accent-red hover:underline text-sm font-semibold"
                      >
                        Wyślij kolejną rezerwację
                      </button>
                    </div>
                  ) : (
                    <form onSubmit={handleReservation} className="text-left space-y-4">
                      <div>
                        <input
                          type="text"
                          required
                          placeholder="Imię i nazwisko *"
                          value={resName}
                          onChange={(e) => setResName(e.target.value)}
                          className={inputClass}
                        />
                      </div>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <input
                          type="email"
                          required
                          placeholder="Email *"
                          value={resEmail}
                          onChange={(e) => setResEmail(e.target.value)}
                          className={inputClass}
                        />
                        <input
                          type="tel"
                          required
                          placeholder="Telefon *"
                          value={resPhone}
                          onChange={(e) => setResPhone(e.target.value)}
                          className={inputClass}
                        />
                      </div>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                          <label className="block text-gray-400 text-sm mb-1">Data odbioru *</label>
                          <input
                            type="date"
                            required
                            min={minDate}
                            value={pickupDate}
                            onChange={(e) => setPickupDate(e.target.value)}
                            className={inputClass}
                          />
                        </div>
                        <div>
                          <label className="block text-gray-400 text-sm mb-1">Data zwrotu *</label>
                          <input
                            type="date"
                            required
                            min={pickupDate || minDate}
                            value={returnDate}
                            onChange={(e) => setReturnDate(e.target.value)}
                            className={inputClass}
                          />
                        </div>
                      </div>
                      {bikes && bikes.length > 0 && (
                        <div>
                          <select
                            value={motorcycleId}
                            onChange={(e) => setMotorcycleId(e.target.value)}
                            className={inputClass}
                          >
                            <option value="">Wybierz motocykl (opcjonalnie)</option>
                            {bikes.filter(b => b.available).map(b => (
                              <option key={b.id} value={b.id}>
                                {b.name} — {b.price_per_day} zł/dzień
                              </option>
                            ))}
                          </select>
                        </div>
                      )}
                      <div>
                        <textarea
                          placeholder="Uwagi (opcjonalnie)"
                          value={notes}
                          onChange={(e) => setNotes(e.target.value)}
                          rows={3}
                          className={`${inputClass} resize-none`}
                        />
                      </div>
                      <div>
                        <label className="flex items-start gap-3 cursor-pointer">
                          <input
                            type="checkbox"
                            required
                            checked={rodoConsent}
                            onChange={(e) => setRodoConsent(e.target.checked)}
                            className="mt-1 w-4 h-4 text-accent-red border-gray-300 rounded focus:ring-accent-red"
                          />
                          <span className="text-xs text-gray-400">
                            Wyrażam zgodę na przetwarzanie moich danych osobowych w celu realizacji rezerwacji,
                            zgodnie z <a href="/polityka-prywatnosci" className="text-accent-red hover:underline">Polityką Prywatności</a>. *
                          </span>
                        </label>
                      </div>
                      {resStatus === 'error' && (
                        <div className="bg-red-900/30 border border-red-500/50 rounded-lg p-4">
                          <p className="text-red-400 text-sm">{resError}</p>
                        </div>
                      )}
                      <button
                        type="submit"
                        disabled={resStatus === 'sending'}
                        className="w-full bg-accent-red text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        {resStatus === 'sending' ? 'Wysyłanie...' : 'Wyślij rezerwację'}
                      </button>
                    </form>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
