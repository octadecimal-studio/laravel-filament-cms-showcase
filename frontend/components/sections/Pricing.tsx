import type { PricingData, Motorcycle } from '@/lib/api';

interface PricingProps {
  pricing: PricingData;
  bikes: Motorcycle[];
}

const categoryLabels: Record<string, string> = {
  sport: 'Sportowy',
  cruiser: 'Cruiser',
  touring: 'Touring',
  adventure: 'Adventure',
  naked: 'Naked',
};

export default function Pricing({ pricing, bikes }: PricingProps) {
  // Jeśli mamy pricing.table (z produkcji), użyj tego
  if (pricing.table && pricing.table.length > 0) {
    return (
      <section id="cennik" className="py-20 bg-white">
        <div className="container mx-auto px-4">
          <div className="text-center mb-12">
            <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
              {pricing.title}
            </h2>
            <p className="text-lg text-gray-medium max-w-2xl mx-auto">
              {pricing.subtitle}
            </p>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full max-w-4xl mx-auto bg-white rounded-xl shadow-md overflow-hidden">
              <thead className="bg-primary-black text-white">
                <tr>
                  <th className="px-6 py-4 text-left font-heading font-bold">Okres wynajmu</th>
                  <th className="px-6 py-4 text-center font-heading font-bold">Cena za dzień</th>
                  {pricing.table[0]?.deposit && (
                    <th className="px-6 py-4 text-center font-heading font-bold">Kaucja</th>
                  )}
                </tr>
              </thead>
              <tbody>
                {pricing.table.map((row, index) => (
                  <tr
                    key={index}
                    className={index % 2 === 0 ? 'bg-gray-light' : 'bg-white'}
                  >
                    <td className="px-6 py-4 font-semibold">{row.period}</td>
                    <td className="px-6 py-4 text-center">
                      <span className="text-accent-red font-bold">{row.price}</span>
                    </td>
                    {row.deposit && (
                      <td className="px-6 py-4 text-center">
                        <span className="font-bold">{row.deposit}</span>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {pricing.disclaimer && (
            <div className="mt-8 max-w-4xl mx-auto text-center">
              <p className="text-gray-medium italic">{pricing.disclaimer}</p>
            </div>
          )}
        </div>
      </section>
    );
  }

  // Dane z API /pricing — per-motorcycle pricing
  if (pricing.motorcycles && pricing.motorcycles.length > 0) {
    return (
      <section id="cennik" className="py-20 bg-white">
        <div className="container mx-auto px-4">
          <div className="text-center mb-12">
            <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
              {pricing.title}
            </h2>
            {pricing.subtitle && (
              <p className="text-lg text-gray-medium max-w-2xl mx-auto">
                {pricing.subtitle}
              </p>
            )}
          </div>

          <div className="overflow-x-auto">
            <table className="w-full max-w-5xl mx-auto bg-white rounded-xl shadow-md overflow-hidden">
              <thead className="bg-primary-black text-white">
                <tr>
                  <th className="px-6 py-4 text-left font-heading font-bold">Motocykl</th>
                  <th className="px-6 py-4 text-center font-heading font-bold">Dzień</th>
                  <th className="px-6 py-4 text-center font-heading font-bold">Tydzień</th>
                  <th className="px-6 py-4 text-center font-heading font-bold">Miesiąc</th>
                  <th className="px-6 py-4 text-center font-heading font-bold">Kaucja</th>
                </tr>
              </thead>
              <tbody>
                {pricing.motorcycles.map((moto, index) => (
                  <tr
                    key={moto.id}
                    className={index % 2 === 0 ? 'bg-gray-light' : 'bg-white'}
                  >
                    <td className="px-6 py-4 font-semibold">{moto.name}</td>
                    <td className="px-6 py-4 text-center">
                      <span className="text-accent-red font-bold">{moto.price_per_day} zł</span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className="font-bold">{moto.price_per_week} zł</span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className="font-bold">{moto.price_per_month} zł</span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className="font-bold">{moto.deposit} zł</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {pricing.notes && pricing.notes.length > 0 && (
            <div className="mt-8 max-w-4xl mx-auto bg-gray-light p-6 rounded-xl">
              <h3 className="font-heading text-xl font-bold mb-4">Uwagi</h3>
              <ul className="space-y-2 text-gray-medium">
                {pricing.notes.map((note) => (
                  <li key={note.id}>• {note.content}</li>
                ))}
              </ul>
            </div>
          )}
        </div>
      </section>
    );
  }

  // Fallback: generuj z bikes (stara metoda)
  const bikesByCategory = bikes.reduce((acc, bike) => {
    const categorySlug = bike.category.slug;
    if (!acc[categorySlug]) {
      acc[categorySlug] = [];
    }
    acc[categorySlug].push(bike);
    return acc;
  }, {} as Record<string, Motorcycle[]>);

  const pricingTable = Object.entries(bikesByCategory).map(([category, categoryBikes]) => {
    const prices = categoryBikes.map(b => b.price_per_day);
    const pricesWeek = categoryBikes.map(b => b.price_per_day * 6);
    const pricesMonth = categoryBikes.map(b => b.price_per_day * 25);
    return {
      category: categoryLabels[category] || categoryBikes[0]?.category.name || category,
      dayMin: Math.min(...prices),
      dayMax: Math.max(...prices),
      weekMin: Math.min(...pricesWeek),
      weekMax: Math.max(...pricesWeek),
      monthMin: Math.min(...pricesMonth),
      monthMax: Math.max(...pricesMonth),
    };
  });

  return (
    <section id="cennik" className="py-20 bg-white">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
            {pricing.title}
          </h2>
          <p className="text-lg text-gray-medium max-w-2xl mx-auto">
            {pricing.subtitle}
          </p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full max-w-4xl mx-auto bg-white rounded-xl shadow-md overflow-hidden">
            <thead className="bg-primary-black text-white">
              <tr>
                <th className="px-6 py-4 text-left font-heading font-bold">Kategoria</th>
                <th className="px-6 py-4 text-center font-heading font-bold">Dzień</th>
                <th className="px-6 py-4 text-center font-heading font-bold">Tydzień</th>
                <th className="px-6 py-4 text-center font-heading font-bold">Miesiąc</th>
              </tr>
            </thead>
            <tbody>
              {pricingTable.map((row, index) => (
                <tr
                  key={index}
                  className={index % 2 === 0 ? 'bg-gray-light' : 'bg-white'}
                >
                  <td className="px-6 py-4 font-semibold">{row.category}</td>
                  <td className="px-6 py-4 text-center">
                    {row.dayMin === row.dayMax ? (
                      <span className="text-accent-red font-bold">{row.dayMin} zł</span>
                    ) : (
                      <span className="text-accent-red font-bold">
                        {row.dayMin} - {row.dayMax} zł
                      </span>
                    )}
                  </td>
                  <td className="px-6 py-4 text-center">
                    {row.weekMin === row.weekMax ? (
                      <span className="font-bold">{row.weekMin} zł</span>
                    ) : (
                      <span className="font-bold">
                        {row.weekMin} - {row.weekMax} zł
                      </span>
                    )}
                  </td>
                  <td className="px-6 py-4 text-center">
                    {row.monthMin === row.monthMax ? (
                      <span className="font-bold">{row.monthMin} zł</span>
                    ) : (
                      <span className="font-bold">
                        {row.monthMin} - {row.monthMax} zł
                      </span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="mt-8 max-w-4xl mx-auto bg-gray-light p-6 rounded-xl">
          <h3 className="font-heading text-xl font-bold mb-4">Uwagi</h3>
          <ul className="space-y-2 text-gray-medium">
            <li>• Wszystkie ceny zawierają pełne ubezpieczenie</li>
            <li>• Wymagana kaucja zwrotna: 2000-5000 zł (w zależności od modelu)</li>
            <li>• Dodatkowe opcje: GPS, kask, ochraniacze - dostępne na życzenie</li>
            <li>• Zniżki dla długoterminowych wypożyczeń (powyżej 1 miesiąca)</li>
          </ul>
        </div>
      </div>
    </section>
  );
}
