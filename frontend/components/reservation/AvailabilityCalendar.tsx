'use client';

import { useEffect, useMemo, useState } from 'react';
import { DayPicker, type DateRange } from 'react-day-picker';
import { addDays, eachDayOfInterval, format, parseISO } from 'date-fns';
import { pl } from 'date-fns/locale';
import 'react-day-picker/style.css';
import { fetchAvailability, type Occupied } from '@/lib/rental-api';
import { calculateTieredPrice, formatBreakdown } from '@/lib/pricing';

type Props = {
  /** Slug lub UUID zasobu (Motorcycle.slug) */
  rentableSlug: string;
  /** Cena za 1 dobe w PLN (z Motorcycle.price_per_day) */
  pricePerDay: number;
  /** Cena za pelny tydzien (z Motorcycle.price_per_week). Jesli > 0, uzywana w algorytmie tiered. */
  pricePerWeek?: number;
  /** Cena za pelny miesiac (z Motorcycle.price_per_month). Jesli > 0, uzywana w algorytmie tiered. */
  pricePerMonth?: number;
  /** Lista godzin (HH:MM) z LocationSettings.pickup_hours (KML-0047). */
  pickupHours?: string[];
  /**
   * Callback wywolywany przy zmianie zakresu (zarowno wybor jak i wyczyszczenie).
   * KML-0047: dodatkowo ISO-string `start_at` / `end_at` (Y-m-d H:i:s) gdy zakres + godziny wybrane.
   */
  onRangeChange: (
    range: { from: Date; to: Date; start_at: string; end_at: string } | null,
    totalDays: number,
    totalAmount: number,
  ) => void;
  /** Liczba miesiecy widocznych jednoczesnie (default 1, sm+: 2) */
  numberOfMonths?: number;
};

const DEFAULT_PICKUP_HOURS = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

/**
 * Skleja datę (Date) z godziną HH:MM do stringa "Y-m-d H:i:s" (lokalna strefa).
 */
function combineDateTime(date: Date, time: string): string {
  const [h, m] = time.split(':').map((v) => parseInt(v, 10));
  const d = new Date(date);
  d.setHours(h, m, 0, 0);
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd} ${hh}:${mi}:00`;
}

/**
 * Kalendarz dostępności motocykla z wyborem zakresu dat.
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
  pricePerWeek,
  pricePerMonth,
  pickupHours,
  onRangeChange,
  numberOfMonths = 2,
}: Props) {
  const hours = useMemo(
    () => (pickupHours && pickupHours.length > 0 ? pickupHours : DEFAULT_PICKUP_HOURS),
    [pickupHours],
  );
  const defaultHour = hours[0] ?? '10:00';

  const [occupied, setOccupied] = useState<Occupied[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [range, setRange] = useState<DateRange | undefined>();
  /**
   * KML-0051: osobne godziny odbioru i zwrotu (przywrocone po regresji KML-0047).
   * start_at = day_from + pickupTime, end_at = day_to + returnTime.
   * Liczba dob = floor((endAt - startAt) / 24h), minimum 1 (kwadratowo, brak roundingu w gore).
   */
  const [pickupTime, setPickupTime] = useState<string>(defaultHour);
  const [returnTime, setReturnTime] = useState<string>(defaultHour);

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
          setError(err instanceof Error ? err.message : 'Błąd pobierania dostępności');
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

  // Datetime stringi (Y-m-d H:i:s) — KML-0051: osobne godziny odbioru i zwrotu.
  const startAt = useMemo(
    () => (range?.from ? combineDateTime(range.from, pickupTime) : null),
    [range?.from, pickupTime],
  );
  const endAt = useMemo(
    () => (range?.to ? combineDateTime(range.to, returnTime) : null),
    [range?.to, returnTime],
  );

  /**
   * KML-0051 (mirror backend Rental::computeBillingDays): floor((endAt - startAt) / 24h), min 1.
   * Pelna doba = 24h. 26h -> 1 doba. 23h -> minimum 1 doba.
   */
  const days = useMemo(() => {
    if (!startAt || !endAt) return 0;
    const startMs = new Date(startAt.replace(' ', 'T')).getTime();
    const endMs = new Date(endAt.replace(' ', 'T')).getTime();
    if (endMs <= startMs) return 0;
    const fullDays = Math.floor((endMs - startMs) / (24 * 3600 * 1000));
    return Math.max(1, fullDays);
  }, [startAt, endAt]);

  // Tiered pricing (KML-0051): mirror backend strategii TieredPerRentablePricingStrategy.
  // Algorytm: miesiac -> tydzien -> dzien (greedy decomposition).
  const breakdown = useMemo(
    () => calculateTieredPrice(days, pricePerDay, pricePerWeek, pricePerMonth),
    [days, pricePerDay, pricePerWeek, pricePerMonth],
  );
  const totalAmount = breakdown.total;

  // Notify parent — minimum 1 doba (same-day = 0 -> blokujemy)
  useEffect(() => {
    if (range?.from && range?.to && !rangeOverlapsBlocked && startAt && endAt && days >= 1) {
      onRangeChange(
        { from: range.from, to: range.to, start_at: startAt, end_at: endAt },
        days,
        totalAmount,
      );
    } else {
      onRangeChange(null, 0, 0);
    }
  }, [range, startAt, endAt, days, totalAmount, rangeOverlapsBlocked, onRangeChange]);

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  if (loading) {
    return (
      <div className="bg-white rounded-lg p-6 shadow-sm">
        <div className="animate-pulse text-gray-500">Ładowanie dostępności…</div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
        Nie udało się pobrać dostępności: {error}
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

      {/* KML-0051: osobne godziny odbioru i zwrotu */}
      <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-md">
        <label className="flex flex-col text-sm">
          <span className="text-gray-600 mb-1">Godzina odbioru</span>
          <select
            value={pickupTime}
            onChange={(e) => setPickupTime(e.target.value)}
            className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-red focus:border-transparent outline-none bg-white"
          >
            {hours.map((h) => (
              <option key={`pickup-${h}`} value={h}>{h}</option>
            ))}
          </select>
        </label>
        <label className="flex flex-col text-sm">
          <span className="text-gray-600 mb-1">Godzina zwrotu</span>
          <select
            value={returnTime}
            onChange={(e) => setReturnTime(e.target.value)}
            className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-red focus:border-transparent outline-none bg-white"
          >
            {hours.map((h) => (
              <option key={`return-${h}`} value={h}>{h}</option>
            ))}
          </select>
        </label>
      </div>

      <div className="mt-4 flex items-center gap-4 text-sm text-gray-600 flex-wrap">
        <div className="flex items-center gap-2">
          <span className="inline-block w-4 h-4 rounded bg-gray-200" />
          <span>Zajęte</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="inline-block w-4 h-4 rounded bg-accent-red" />
          <span>Wybrany zakres</span>
        </div>
      </div>

      {rangeOverlapsBlocked && (
        <div className="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-yellow-800 text-sm">
          Wybrany zakres zawiera niedostępne dni. Wybierz inny termin.
        </div>
      )}

      {range?.from && range?.to && !rangeOverlapsBlocked && days > 0 && (
        <div className="mt-4 bg-gray-50 rounded-lg p-4">
          <div className="flex justify-between items-center">
            <div>
              <div className="text-sm text-gray-600">
                {format(range.from, 'd MMM', { locale: pl })} {pickupTime} —{' '}
                {format(range.to, 'd MMM yyyy', { locale: pl })} {returnTime}
              </div>
              <div className="text-xs text-gray-500">
                {days} {days === 1 ? 'doba' : days < 5 ? 'doby' : 'dób'}
              </div>
            </div>
            <div className="text-right">
              <div className="text-xs text-gray-500">Razem</div>
              <div className="text-2xl font-bold text-accent-red">
                {totalAmount.toLocaleString('pl-PL')} zł
              </div>
            </div>
          </div>
          {(breakdown.months > 0 || breakdown.weeks > 0) && (
            <div className="mt-3 pt-3 border-t border-gray-200 text-xs text-gray-600">
              {formatBreakdown(breakdown)}
            </div>
          )}
        </div>
      )}

      <style jsx global>{`
        .rdp-rental {
          --rdp-accent-color: #16a34a;
          --rdp-accent-background-color: #dcfce7;
          font-size: 14px;
        }
        .rdp-day-blocked {
          text-decoration: line-through;
          color: #991b1b !important;
          background-color: #fee2e2 !important;
        }
        .rdp-day-selected {
          background-color: var(--rdp-accent-color) !important;
          color: white !important;
        }
        .rdp-day-selected.rdp-range_start,
        .rdp-day-selected.rdp-range_end {
          background-color: #15803d !important;
        }
      `}</style>
    </div>
  );
}
