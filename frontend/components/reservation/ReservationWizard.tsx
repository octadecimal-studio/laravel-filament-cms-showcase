'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { format } from 'date-fns';
import { pl } from 'date-fns/locale';
import Image from 'next/image';
import Link from 'next/link';
import { createRental, initPayment, RentalApiError } from '@/lib/rental-api';
import AvailabilityCalendar from './AvailabilityCalendar';
import type { Motorcycle } from '@/lib/api';

type Range = {
  from: Date;
  to: Date;
  /** KML-0047: ISO-string Y-m-d H:i:s (lokalna strefa). */
  start_at: string;
  /** KML-0047: ISO-string Y-m-d H:i:s (lokalna strefa). */
  end_at: string;
  days: number;
  total: number;
};

type Props = {
  motorcycle: Motorcycle;
  /** KML-0047: dostepne godziny odbioru/zwrotu (z LocationSettings.pickup_hours). */
  pickupHours?: string[];
};

/**
 * Wizard rezerwacji 3-krokowy:
 *  1. Motocykl (preselected, pokaz danych)
 *  2. Daty (AvailabilityCalendar)
 *  3. Dane + RODO (RHF + zod) -> POST /api/rentals
 *
 * Po sukcesie POST /api/rentals:
 *  - jesli requires_payment === true -> POST /payment + redirect (G3)
 *  - jesli false -> pokaz sukces (rezerwacja potwierdzona)
 *
 * @see KML-0062 (G2)
 * @see KML-0063 (G3)
 */
const personalSchema = z.object({
  name: z.string().min(2, 'Imię i nazwisko musi mieć min. 2 znaki').max(255),
  email: z.string().email('Niepoprawny adres email').max(255),
  phone: z
    .string()
    .min(6, 'Numer telefonu musi mieć min. 6 znaków')
    .max(30, 'Numer telefonu jest za długi'),
  message: z.string().max(1000, 'Wiadomość jest za długa').optional(),
  gdpr_consent: z
    .boolean()
    .refine((v) => v === true, { message: 'Zgoda RODO jest wymagana' }),
  terms_consent: z
    .boolean()
    .refine((v) => v === true, { message: 'Akceptacja regulaminu jest wymagana' }),
});

type PersonalForm = z.infer<typeof personalSchema>;

export default function ReservationWizard({ motorcycle, pickupHours }: Props) {
  const [step, setStep] = useState<1 | 2 | 3>(1);
  const [range, setRange] = useState<Range | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<PersonalForm>({
    resolver: zodResolver(personalSchema),
    defaultValues: {
      gdpr_consent: false,
      terms_consent: false,
    },
  });

  const onSubmit = async (data: PersonalForm) => {
    if (!range) {
      setSubmitError('Wybierz termin wynajmu w kroku 2.');
      setStep(2);
      return;
    }

    setSubmitting(true);
    setSubmitError(null);

    try {
      const rental = await createRental({
        rentable: motorcycle.slug,
        // KML-0046 legacy (wstecznie kompatybilne)
        start_date: format(range.from, 'yyyy-MM-dd'),
        end_date: format(range.to, 'yyyy-MM-dd'),
        // KML-0047: backend preferuje start_at/end_at jesli obecne
        start_at: range.start_at,
        end_at: range.end_at,
        name: data.name,
        email: data.email,
        phone: data.phone,
        message: data.message || undefined,
        // total_amount w groszach (P24 wymaga jednostek mniejszych)
        total_amount: Math.round(range.total * 100),
        currency: 'PLN',
        locale: 'pl',
        gdpr_consent: true,
      });

      // G3: jesli wymaga platnosci -> P24 redirect (P24 zwroci na /rezerwacja/sukces lub /rezerwacja/blad)
      if (rental.requires_payment) {
        try {
          const payment = await initPayment(rental.id);
          window.location.assign(payment.redirect_url);
          return;
        } catch (err) {
          const code =
            err instanceof RentalApiError ? String(err.status) : 'unknown';
          window.location.assign(
            `/rezerwacja/blad?rental=${encodeURIComponent(rental.id)}&reason=failed&code=${code}`,
          );
          return;
        }
      }

      // Brak platnosci wymaganej -> bezposrednio na strone sukcesu
      window.location.assign(
        `/rezerwacja/sukces?rental=${encodeURIComponent(rental.id)}`,
      );
    } catch (err) {
      if (err instanceof RentalApiError) {
        if (err.status === 409) {
          setSubmitError(
            'Wybrany termin nie jest już dostępny. Wróć do kroku 2 i wybierz inne dni.',
          );
        } else if (err.status === 422) {
          const payload = err.payload as { errors?: Record<string, string[]> };
          const firstError =
            payload?.errors && Object.values(payload.errors)[0]?.[0];
          setSubmitError(firstError || err.message);
        } else if (err.status === 404) {
          setSubmitError('Motocykl nie został znaleziony.');
        } else {
          setSubmitError(`${err.message} (HTTP ${err.status})`);
        }
      } else if (err instanceof Error) {
        setSubmitError(err.message);
      } else {
        setSubmitError('Błąd zapisu rezerwacji');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div id="wizard-rezerwacji" className="bg-white rounded-xl shadow-lg p-6 lg:p-10">
      {/* Stepper */}
      <div className="flex items-center justify-center gap-2 sm:gap-4 mb-8">
        {([1, 2, 3] as const).map((n, idx) => (
          <div key={n} className="flex items-center gap-2 sm:gap-4">
            <div
              className={`w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm transition-colors ${
                step === n
                  ? 'bg-accent-red text-white'
                  : step > n
                    ? 'bg-green-500 text-white'
                    : 'bg-gray-200 text-gray-500'
              }`}
            >
              {step > n ? '✓' : n}
            </div>
            <span
              className={`text-sm hidden sm:inline ${
                step === n ? 'font-semibold' : 'text-gray-500'
              }`}
            >
              {n === 1 ? 'Motocykl' : n === 2 ? 'Termin' : 'Dane'}
            </span>
            {idx < 2 && <div className="w-8 sm:w-12 h-px bg-gray-300" />}
          </div>
        ))}
      </div>

      {/* === KROK 1: Motocykl === */}
      {step === 1 && (
        <div>
          <h2 className="font-heading text-2xl font-bold mb-6">Wybrany motocykl</h2>
          <div className="flex flex-col sm:flex-row gap-6 items-start">
            {motorcycle.main_image && (
              <div className="relative w-full sm:w-64 h-48 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                <Image
                  src={motorcycle.main_image.url}
                  alt={motorcycle.name}
                  fill
                  className="object-contain"
                  unoptimized={motorcycle.main_image.url.startsWith('http')}
                />
              </div>
            )}
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-2">
                <span className="bg-accent-red text-white px-2 py-0.5 rounded text-xs font-semibold">
                  {motorcycle.category.name}
                </span>
                {motorcycle.brand.name && (
                  <span className="text-gray-medium text-sm">
                    {motorcycle.brand.name}
                  </span>
                )}
              </div>
              <h3 className="font-heading text-xl font-bold mb-2">
                {motorcycle.name}
              </h3>
              <p className="text-gray-medium text-sm mb-3">
                {motorcycle.specs?.engine && <>Silnik: {motorcycle.specs.engine}</>}
                {motorcycle.specs?.power && <> · Moc: {motorcycle.specs.power}</>}
              </p>
              <div className="text-2xl font-bold text-accent-red">
                {motorcycle.price_per_day} zł
                <span className="text-sm font-normal text-gray-500"> / dzień</span>
              </div>
            </div>
          </div>
          <div className="mt-8 flex justify-end">
            <button
              type="button"
              onClick={() => setStep(2)}
              className="bg-accent-red text-white px-8 py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors"
            >
              Dalej: wybierz termin →
            </button>
          </div>
        </div>
      )}

      {/* === KROK 2: Daty === */}
      {step === 2 && (
        <div>
          <h2 className="font-heading text-2xl font-bold mb-6">Wybierz termin</h2>
          <AvailabilityCalendar
            rentableSlug={motorcycle.slug}
            pricePerDay={motorcycle.price_per_day}
            pricePerWeek={motorcycle.price_per_week}
            pricePerMonth={motorcycle.price_per_month}
            pickupHours={pickupHours}
            onRangeChange={(r, days, total) => {
              if (r) setRange({ from: r.from, to: r.to, start_at: r.start_at, end_at: r.end_at, days, total });
              else setRange(null);
            }}
          />
          <div className="mt-8 flex justify-between gap-3">
            <button
              type="button"
              onClick={() => setStep(1)}
              className="bg-gray-100 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-200 transition-colors"
            >
              ← Wstecz
            </button>
            <button
              type="button"
              onClick={() => setStep(3)}
              disabled={!range}
              className={`px-8 py-3 rounded-lg font-semibold transition-colors ${
                range
                  ? 'bg-accent-red text-white hover:bg-red-700'
                  : 'bg-gray-300 text-gray-500 cursor-not-allowed'
              }`}
            >
              {range ? 'Dalej: dane kontaktowe →' : 'Wybierz termin'}
            </button>
          </div>
        </div>
      )}

      {/* === KROK 3: Dane + RODO === */}
      {step === 3 && (
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          <h2 className="font-heading text-2xl font-bold mb-6">Dane do rezerwacji</h2>

          {/* Podsumowanie zakresu */}
          {range && (
            <div className="bg-gray-50 rounded-lg p-4 mb-6 flex justify-between items-center flex-wrap gap-3">
              <div>
                <div className="text-xs text-gray-500">Termin</div>
                <div className="font-semibold">
                  {format(range.from, 'd MMM', { locale: pl })} {range.start_at.slice(11, 16)} —{' '}
                  {format(range.to, 'd MMM yyyy', { locale: pl })} {range.end_at.slice(11, 16)}
                </div>
                <div className="text-xs text-gray-500">
                  {range.days} {range.days === 1 ? 'doba' : range.days < 5 ? 'doby' : 'dób'}
                </div>
              </div>
              <div className="text-right">
                <div className="text-xs text-gray-500">Razem</div>
                <div className="text-2xl font-bold text-accent-red">
                  {range.total.toLocaleString('pl-PL')} zł
                </div>
              </div>
            </div>
          )}

          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Imię i nazwisko *
              </label>
              <input
                type="text"
                {...register('name')}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-red focus:border-transparent outline-none"
                aria-invalid={!!errors.name}
              />
              {errors.name && (
                <p className="text-red-600 text-sm mt-1">{errors.name.message}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Email *
              </label>
              <input
                type="email"
                {...register('email')}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-red focus:border-transparent outline-none"
                aria-invalid={!!errors.email}
              />
              {errors.email && (
                <p className="text-red-600 text-sm mt-1">{errors.email.message}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Telefon *
              </label>
              <input
                type="tel"
                {...register('phone')}
                placeholder="+48 600 100 200"
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-red focus:border-transparent outline-none"
                aria-invalid={!!errors.phone}
              />
              {errors.phone && (
                <p className="text-red-600 text-sm mt-1">{errors.phone.message}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Wiadomość (opcjonalnie)
              </label>
              <textarea
                {...register('message')}
                rows={3}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-red focus:border-transparent outline-none resize-none"
              />
              {errors.message && (
                <p className="text-red-600 text-sm mt-1">{errors.message.message}</p>
              )}
            </div>

            <div className="space-y-3 pt-2">
              <label className="flex items-start gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  {...register('gdpr_consent')}
                  className="mt-1 w-4 h-4 text-accent-red rounded focus:ring-accent-red"
                />
                <span className="text-sm text-gray-700">
                  Wyrażam zgodę na przetwarzanie moich danych osobowych w celu
                  realizacji rezerwacji zgodnie z{' '}
                  <Link
                    href="/polityka-prywatnosci"
                    className="text-accent-red hover:underline"
                    target="_blank"
                  >
                    Polityką Prywatności
                  </Link>
                  . *
                </span>
              </label>
              {errors.gdpr_consent && (
                <p className="text-red-600 text-sm">{errors.gdpr_consent.message}</p>
              )}

              <label className="flex items-start gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  {...register('terms_consent')}
                  className="mt-1 w-4 h-4 text-accent-red rounded focus:ring-accent-red"
                />
                <span className="text-sm text-gray-700">
                  Akceptuję{' '}
                  <Link
                    href="/regulamin"
                    className="text-accent-red hover:underline"
                    target="_blank"
                  >
                    regulamin wynajmu
                  </Link>
                  . *
                </span>
              </label>
              {errors.terms_consent && (
                <p className="text-red-600 text-sm">{errors.terms_consent.message}</p>
              )}
            </div>
          </div>

          {submitError && (
            <div className="mt-6 bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 text-sm">
              {submitError}
            </div>
          )}

          <div className="mt-8 flex justify-between gap-3">
            <button
              type="button"
              onClick={() => setStep(2)}
              disabled={submitting}
              className="bg-gray-100 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-200 transition-colors disabled:opacity-50"
            >
              ← Wstecz
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="bg-accent-red text-white px-8 py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {submitting ? 'Wysyłanie…' : 'Złóż rezerwację i przejdź do płatności'}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
