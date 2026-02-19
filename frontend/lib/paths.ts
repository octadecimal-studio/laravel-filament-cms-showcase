export const MONDAY_RESERVATION_FORM_URL = 'https://forms.monday.com/forms/ed47132e0b7239abdf0ec7e5504f2883?r=euc1';

// Helper do ścieżek obrazów - działa w dev i production
export function getAssetPath(relativePath: string): string {
  // Użyj NEXT_PUBLIC_BASE_PATH jeśli ustawione, w przeciwnym razie puste (root domain)
  const basePath = process.env.NEXT_PUBLIC_BASE_PATH || '';
  // Zapewnij, że relativePath zaczyna się od /
  const cleanPath = relativePath.startsWith('/') ? relativePath : `/${relativePath}`;
  return `${basePath}${cleanPath}`;
}

// Alias dla kompatybilności
export const getImagePath = getAssetPath;

/** Linki hash (#o-nas) → /#section. Regulamin i polityka → oddzielne podstrony. */
export function normalizeHashHref(href: string): string {
  if (typeof href !== 'string') return href;
  if (href === '#regulamin') return '/regulamin';
  if (href === '#polityka-prywatnosci') return '/polityka-prywatnosci';
  return href.startsWith('#') ? `/${href}` : href;
}
