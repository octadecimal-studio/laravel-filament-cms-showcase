'use client';

import { useState, useMemo } from 'react';
import BikeCard from '../BikeCard';
import type { FleetData, Motorcycle } from '@/lib/api';

interface FleetProps {
  fleet: FleetData;
  initialBikes: Motorcycle[];
  totalBikes: number;
}

export default function Fleet({ fleet, initialBikes, totalBikes }: FleetProps) {
  const [selectedCategory, setSelectedCategory] = useState('wszystkie');
  const [bikes, setBikes] = useState<Motorcycle[]>(initialBikes);

  // Użyj categories z API
  const categories = fleet.categories || [];

  // Filtrowanie po kategorii (client-side dla mock, w przyszłości server-side)
  const filteredBikes = useMemo(() => {
    if (selectedCategory === 'wszystkie') {
      return bikes;
    }
    return bikes.filter(bike => bike.category.slug === selectedCategory);
  }, [bikes, selectedCategory]);

  return (
    <section id="motocykle" className="py-20 bg-white">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
            {fleet.title}
          </h2>
          <p className="text-lg text-gray-medium max-w-2xl mx-auto">
            {fleet.subtitle}
          </p>
        </div>

        {/* Kategorie jako tabs (jeśli dostępne w API) */}
        {categories.length > 0 && (
          <div className="mb-8 flex flex-wrap justify-center gap-2">
            {categories.map((cat) => (
              <button
                key={cat.value}
                onClick={() => setSelectedCategory(cat.value)}
                className={`px-6 py-2 rounded-lg font-semibold transition-colors ${
                  selectedCategory === cat.value || (cat.active && selectedCategory === 'wszystkie')
                    ? 'bg-accent-red text-white'
                    : 'bg-gray-light text-gray-dark hover:bg-gray-300'
                }`}
              >
                {cat.label}
              </button>
            ))}
          </div>
        )}

        {/* Lista motocykli */}
        {filteredBikes.length > 0 ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {filteredBikes.map(bike => (
              <BikeCard key={bike.id} bike={bike} />
            ))}
          </div>
        ) : (
          <div className="text-center py-12">
            <p className="text-gray-medium text-lg">Nie znaleziono motocykli spełniających kryteria</p>
          </div>
        )}

        {/* Info o całkowitej liczbie */}
        {totalBikes > filteredBikes.length && (
          <div className="mt-8 text-center">
            <p className="text-gray-medium">
              Wyświetlono {filteredBikes.length} z {totalBikes} motocykli
            </p>
          </div>
        )}
      </div>
    </section>
  );
}
