import Link from 'next/link';

export default function NotFound() {
  return (
    <main className="min-h-screen flex flex-col items-center justify-center bg-gray-50 px-4">
      <h1 className="font-heading text-4xl font-bold text-primary-black mb-2">
        Strona nie znaleziona
      </h1>
      <p className="text-gray-600 mb-6 text-center max-w-md">
        Adres może być nieprawidłowy lub strona została przeniesiona.
      </p>
      <Link
        href="/"
        className="bg-accent-red text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors"
      >
        Wróć na stronę główną
      </Link>
    </main>
  );
}
