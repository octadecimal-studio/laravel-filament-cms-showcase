/**
 * Tiered pricing — algorytm "greedy": miesiac → tydzien → dzien.
 *
 * Front-endowy mirror strategii TieredPerRentablePricingStrategy z pakietu
 * octadecimalhq/reservation-system (KML-0051). Algorytm musi byc identyczny po
 * obu stronach — backend i frontend daja te same wyniki dla tego samego inputu.
 *
 * Algorytm:
 *   N      = liczba dni rezerwacji
 *   months = floor(N / 30) jesli monthly > 0
 *   rem    = N - months * 30
 *   weeks  = floor(rem / 7) jesli weekly > 0
 *   days   = rem - weeks * 7
 *   total  = months * monthly + weeks * weekly + days * daily
 *
 * Przyklady (BMW M1000R Competition: 600/3700/13000):
 *   - 6 dni  → 6 * 600 = 3600
 *   - 7 dni  → 1 * 3700 = 3700
 *   - 9 dni  → 3700 + 2 * 600 = 4900
 *   - 30 dni → 13000
 *   - 37 dni → 13000 + 3700 = 16700
 */
export type PricingBreakdown = {
  total: number;
  months: number;
  weeks: number;
  days: number;
  monthly: number;
  weekly: number;
  daily: number;
};

export function calculateTieredPrice(
  totalDays: number,
  daily: number,
  weekly?: number | null,
  monthly?: number | null,
): PricingBreakdown {
  const d = Math.max(0, daily || 0);
  const w = Math.max(0, weekly || 0);
  const m = Math.max(0, monthly || 0);

  const empty: PricingBreakdown = {
    total: 0,
    months: 0,
    weeks: 0,
    days: 0,
    monthly: m,
    weekly: w,
    daily: d,
  };

  if (totalDays <= 0 || (d <= 0 && w <= 0 && m <= 0)) return empty;

  let remaining = totalDays;
  let months = 0;
  if (m > 0) {
    months = Math.floor(remaining / 30);
    remaining -= months * 30;
  }

  let weeks = 0;
  if (w > 0) {
    weeks = Math.floor(remaining / 7);
    remaining -= weeks * 7;
  }

  const days = remaining;
  const total = months * m + weeks * w + days * d;

  return {
    total,
    months,
    weeks,
    days,
    monthly: m,
    weekly: w,
    daily: d,
  };
}

/**
 * Format breakdown jako string do wyswietlenia uzytkownikowi.
 *
 * Przyklady:
 *   - 6 dni: "6 dni × 600 zł = 3600 zł"
 *   - 7 dni: "1 tydzien × 3700 zł = 3700 zł"
 *   - 9 dni: "1 tydzien × 3700 zł + 2 dni × 600 zł = 4900 zł"
 *   - 37 dni: "1 miesiac × 13000 zł + 1 tydzien × 3700 zł = 16700 zł"
 */
export function formatBreakdown(b: PricingBreakdown): string {
  const parts: string[] = [];
  if (b.months > 0) {
    const label = b.months === 1 ? 'miesiąc' : b.months < 5 ? 'miesiące' : 'miesięcy';
    parts.push(`${b.months} ${label} × ${b.monthly.toLocaleString('pl-PL')} zł`);
  }
  if (b.weeks > 0) {
    const label = b.weeks === 1 ? 'tydzień' : b.weeks < 5 ? 'tygodnie' : 'tygodni';
    parts.push(`${b.weeks} ${label} × ${b.weekly.toLocaleString('pl-PL')} zł`);
  }
  if (b.days > 0) {
    const label = b.days === 1 ? 'dzień' : 'dni';
    parts.push(`${b.days} ${label} × ${b.daily.toLocaleString('pl-PL')} zł`);
  }
  return parts.join(' + ');
}
