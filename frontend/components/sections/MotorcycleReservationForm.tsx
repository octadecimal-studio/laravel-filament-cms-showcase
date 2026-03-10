'use client';

import { useState } from 'react';
import { submitReservation } from '@/lib/api';
import type { Motorcycle } from '@/lib/api';

interface MotorcycleReservationFormProps {
  motorcycle: Motorcycle;
}

export default function MotorcycleReservationForm({ motorcycle }: MotorcycleReservationFormProps) {
  const [resName, setResName] = useState('');
  const [resEmail, setResEmail] = useState('');
  const [resPhone, setResPhone] = useState('');
  const [pickupDate, setPickupDate] = useState('');
  const [returnDate, setReturnDate] = useState('');
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
        motorcycle_id: motorcycle.id,
        notes: notes ? `[${motorcycle.name}] ${notes}` : `[${motorcycle.name}]`,
        rodo_consent: rodoConsent,
      });
      setResStatus('success');
      setResName('');
      setResEmail('');
      setResPhone('');
      setPickupDate('');
      setReturnDate('');
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
    <div id="rezerwacja" className="bg-white rounded-xl shadow-lg p-6 lg:p-12 mb-8">
      <div className="max-w-2xl mx-auto">
        <div className="text-center mb-8">
          <h2 className="font-heading text-3xl font-bold mb-2">
            Zarezerwuj {motorcycle.brand?.name} {motorcycle.name}
          </h2>
          <p className="text-gray-medium">
            Wypełnij formularz, a skontaktujemy się z&nbsp;Tobą
          </p>
        </div>

        {resStatus === 'success' ? (
          <div className="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
            <p className="text-green-700 font-semibold mb-2">Rezerwacja wysłana!</p>
            <p className="text-gray-600 text-sm mb-4">
              Dziękujemy za zgłoszenie. Skontaktujemy się z Tobą w celu potwierdzenia rezerwacji motocykla <strong>{motorcycle.name}</strong>.
            </p>
            <button
              type="button"
              onClick={() => setResStatus('idle')}
              className="text-accent-red hover:underline text-sm font-semibold"
            >
              Wyślij kolejną rezerwację
            </button>
          </div>
        ) : (
          <form onSubmit={handleReservation} className="space-y-4">
            {/* Wybrany motocykl (readonly) */}
            <div className="bg-gray-50 rounded-lg p-4 border border-gray-200">
              <label className="block text-gray-500 text-sm mb-1">Wybrany motocykl</label>
              <p className="font-semibold text-lg">{motorcycle.brand?.name} {motorcycle.name}</p>
              <p className="text-accent-red font-medium">{motorcycle.price_per_day} zł/dzień</p>
            </div>

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
                <label className="block text-gray-600 text-sm mb-1">Data odbioru *</label>
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
                <label className="block text-gray-600 text-sm mb-1">Data zwrotu *</label>
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
                <span className="text-xs text-gray-600">
                  Wyrażam zgodę na przetwarzanie moich danych osobowych w celu realizacji rezerwacji,
                  zgodnie z <a href="/polityka-prywatnosci" className="text-accent-red hover:underline">Polityką Prywatności</a>. *
                </span>
              </label>
            </div>

            {resStatus === 'error' && (
              <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                <p className="text-red-600 text-sm">{resError}</p>
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
      </div>
    </div>
  );
}
