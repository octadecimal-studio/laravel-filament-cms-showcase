'use client';

import { useEffect, useMemo, useState } from 'react';
import { DayPicker, type DateRange } from 'react-day-picker';
import { addDays, differenceInCalendarDays, eachDayOfInterval, format, parseISO } from 'date-fns';
import { pl } from 'date-fns/locale';
import 'react-day-picker/style.css';
import { fetchAvailability, type Occupied } from '@/lib/rental-api';

type Props = {
  /** Slug lub UUID zasobu (Motorcycle.slug) */
  rentableSlug: string;
  /** Cena za 1 dobe w PLN (z Motorcycle.price_per_day) */
  pricePerDay: number;
  /** Callback wywolywany przy zmianie zakresu (zarowno wybor jak i wyczyszczenie) */
  onRangeChange: (range: { from: Date; to: Date } | null, totalDays: number, totalAmount: number) => void;
  /** Liczba miesiecy widocznych jednoczesnie (default 1, sm+: 2) */
  numberOfMonths?: number;
};

/**
 * Kalendarz dostepnosci motocykla z wyborem zakresu dat.
 *
 * Zachowanie:
 *  - przy mount fetchuje GET /api/rentals/availability/{slug} dla today..+180 dni
 *  - blokuje (disabled) wszystkie dni w przedzialach occupied (rental + block)
 *  - blokuje daty przeszle
 *  - obsluguje wybor range (from/to)
 *  - waliduje: zakres NIE moze nachodzic na zadne occupied (jesli user wybierze date
 *    przed blokada, a "to" za blokada -> komunikat o niepoprawnym zakresie)
 *  - wylicza i pokazuje cene = liczba_dni * pricePerDay
 *
 * Wzorzec UX: blizne-art-cms (kalendarz w wizard rezerwacji).
 *
 * @see KML-0061 (G1)
 */
export default function AvailabilityCalendar({
  rentableSlug,
  pricePerDay,
  onRangeChange,
  numberOfMonths = 2,
}: Props) {
  const [occupied, setOccupied] = useState<Occupied[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [range, setRange] = useState<DateRange | undefined>();

  // Fetch availability po mount
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);

    const today = new Date();
    const from = format(today, 'yyyy-MM-dd');
    const to = format(addDays(today, 180), 'yyyy-MM-dd');

    fetchAvailability(rentableSlug, { from, to })
      .then((data) => {
        if (!cancelled) {
          setOccupied(data.occupied);
          setLoading(false);
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : 'Blad pobierania dostepnosci');
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [rentableSlug]);

  // Lista zablokowanych dni (rozwijane z range start..end inclusive)
  const blockedDays = useMemo(() => {
    return occupied.flatMap((o) =>
      eachDayOfInterval({ start: parseISO(o.start), end: parseISO(o.end) }),
    );
  }, [occupied]);

  // Zakres nachodzi na blokade?
  const rangeOverlapsBlocked = useMemo(() => {
    if (!range?.from || !range?.to) return false;
    const days = eachDayOfInterval({ start: range.from, end: range.to });
    return days.some((d) =>
      blockedDays.some((b) => b.toDateString() === d.toDateString()),
    );
  }, [range, blockedDays]);

  // Wyliczenie ceny
  const days = useMemo(() => {
    if (!range?.from || !range?.to) return 0;
    return differenceInCalendarDays(range.to, range.from) + 1;
  }, [range]);

  const totalAmount = days * pricePerDay;

  // Notify parent
  useEffect(() => {
    if (range?.from && range?.to && !rangeOverlapsBlocked) {
      onRangeChange({ from: range.from, to: range.to }, days, totalAmount);
    } else {
      onRangeChange(null, 0, 0);
    }
  }, [range, days, totalAmount, rangeOverlapsBlocked, onRangeChange]);

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  if (loading) {
    return (
      <div className="bg-white rounded-lg p-6 shadow-sm">
        <div className="animate-pulse text-gray-500">Ladowanie dostepnosci…</div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
        Nie udalo sie pobrac dostepnosci: {error}
      </div>
    );
  }

  return (
    <div className="bg-white rounded-lg p-4 sm:p-6 shadow-sm">
      <DayPicker
        mode="range"
        selected={range}
        onSelect={setRange}
        locale={pl}
        weekStartsOn={1}
        numberOfMonths={numberOfMonths}
        disabled={[{ before: today }, ...blockedDays]}
        modifiers={{
          blocked: blockedDays,
        }}
        modifiersClassNames={{
          blocked: 'rdp-day-blocked',
          selected: 'rdp-day-selected',
        }}
        className="rdp-rental"
      />

      <div className="mt-4 flex items-center gap-4 text-sm text-gray-600 flex-wrap">
        <div className="flex items-center gap-2">
          <span className="inline-block w-4 h-4 rounded bg-gray-200" />
          <span>Zajete</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="inline-block w-4 h-4 rounded bg-accent-red" />
          <span>Wybrany zakres</span>
        </div>
      </div>

      {rangeOverlapsBlocked && (
        <div className="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-yellow-800 text-sm">
          Wybrany zakres zawiera niedostepne dni. Wybierz inny termin.
        </div>
      )}

      {range?.from && range?.to && !rangeOverlapsBlocked && days > 0 && (
        <div className="mt-4 bg-gray-50 rounded-lg p-4 flex justify-between items-center">
          <div>
            <div className="text-sm text-gray-600">
              {format(range.from, 'd MMM', { locale: pl })} —{' '}
              {format(range.to, 'd MMM yyyy', { locale: pl })}
            </div>
            <div className="text-xs text-gray-500">
              {days} {days === 1 ? 'dzien' : days < 5 ? 'dni' : 'dni'}
            </div>
          </div>
          <div className="text-right">
            <div className="text-xs text-gray-500">Razem</div>
            <div className="text-2xl font-bold text-accent-red">
              {totalAmount.toLocaleString('pl-PL')} zl
            </div>
          </div>
        </div>
      )}

      <style jsx global>{`
        .rdp-rental {
          --rdp-accent-color: #dc2626;
          --rdp-accent-background-color: #fef2f2;
          font-size: 14px;
        }
        .rdp-day-blocked {
          text-decoration: line-through;
          color: #9ca3af;
          background-color: #f3f4f6;
        }
        .rdp-day-selected {
          background-color: var(--rdp-accent-color) !important;
          color: white !important;
        }
      `}</style>
    </div>
  );
}
