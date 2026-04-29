/**
 * Klient API dla modulu rezerwacji (octadecimalhq/reservation-system).
 *
 * Endpointy:
 *  - GET  /api/rentals/availability/{rentable}     E1 KML-0056
 *  - POST /api/rentals                             E2 KML-0057
 *  - POST /api/rentals/{id}/payment                E3 KML-0058
 *
 * Backend: cms.example-rental.test (Laravel + reservation-system).
 */

const API_DOMAIN =
  process.env.NEXT_PUBLIC_API_DOMAIN || 'https://cms.example-rental.test';

const API_BASE = `${API_DOMAIN}/api`;

export type OccupiedRental = {
  start: string;
  end: string;
  type: 'rental';
  status: 'pending' | 'confirmed' | 'paid';
};

export type OccupiedBlock = {
  start: string;
  end: string;
  type: 'block';
  reason: string | null;
};

export type Occupied = OccupiedRental | OccupiedBlock;

export type AvailabilityResponse = {
  rentable: { id: string | number; slug: string | null; name: string | null };
  from: string;
  to: string;
  occupied: Occupied[];
};

export type CreateRentalPayload = {
  rentable: string;
  /** Y-m-d (legacy KML-0046). Wymagane dla wstecznej kompatybilnosci. */
  start_date: string;
  /** Y-m-d (legacy KML-0046). */
  end_date: string;
  /** KML-0047: Y-m-d H:i:s lub ISO. Backend preferuje to pole gdy obecne. */
  start_at?: string;
  /** KML-0047: Y-m-d H:i:s lub ISO. */
  end_at?: string;
  name: string;
  email: string;
  phone: string;
  message?: string;
  qty?: number;
  total_amount?: number;
  currency?: string;
  locale?: string;
  gdpr_consent: boolean;
};

export type CreateRentalResponse = {
  id: string;
  status: 'pending' | 'confirmed';
  total_amount: number;
  currency: string;
  requires_payment: boolean;
  rentable: { id: string | number; slug: string | null; name: string | null };
  start_date: string;
  end_date: string;
};

export type ValidationError = {
  message: string;
  errors: Record<string, string[]>;
};

export type ConflictError = {
  message: string;
  code: 'rental_conflict';
};

export type CreatePaymentResponse = {
  redirect_url: string;
  token: string;
};

export class RentalApiError extends Error {
  constructor(
    public status: number,
    public payload: unknown,
    message: string,
  ) {
    super(message);
    this.name = 'RentalApiError';
  }
}

async function parseError(res: Response): Promise<RentalApiError> {
  let payload: unknown = null;
  try {
    payload = await res.json();
  } catch {
    // brak json
  }
  const msg =
    (payload &&
      typeof payload === 'object' &&
      'message' in payload &&
      typeof (payload as { message: unknown }).message === 'string'
      ? (payload as { message: string }).message
      : null) || `HTTP ${res.status}`;
  return new RentalApiError(res.status, payload, msg);
}

export async function fetchAvailability(
  rentable: string,
  options?: { from?: string; to?: string; signal?: AbortSignal },
): Promise<AvailabilityResponse> {
  const sp = new URLSearchParams();
  if (options?.from) sp.set('from', options.from);
  if (options?.to) sp.set('to', options.to);
  const qs = sp.toString();
  const url = `${API_BASE}/rentals/availability/${encodeURIComponent(rentable)}${qs ? `?${qs}` : ''}`;

  const res = await fetch(url, {
    cache: 'no-store',
    headers: { Accept: 'application/json' },
    signal: options?.signal,
  });
  if (!res.ok) {
    throw await parseError(res);
  }
  return res.json();
}

export async function createRental(
  payload: CreateRentalPayload,
): Promise<CreateRentalResponse> {
  const res = await fetch(`${API_BASE}/rentals`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    throw await parseError(res);
  }
  return res.json();
}

export async function initPayment(
  rentalId: string,
): Promise<CreatePaymentResponse> {
  const res = await fetch(
    `${API_BASE}/rentals/${encodeURIComponent(rentalId)}/payment`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
    },
  );
  if (!res.ok) {
    throw await parseError(res);
  }
  return res.json();
}
