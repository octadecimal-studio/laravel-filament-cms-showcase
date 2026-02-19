import { FiStar } from 'react-icons/fi';
import Image from 'next/image';
import { getAssetPath } from '@/lib/paths';
import type { TestimonialsData } from '@/lib/api';

interface TestimonialsProps {
  testimonials: TestimonialsData;
}

export default function Testimonials({ testimonials }: TestimonialsProps) {
  if (!testimonials.items || testimonials.items.length === 0) {
    return null;
  }

  return (
    <section className="py-20 bg-gray-light">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
            {testimonials.title}
          </h2>
          <p className="text-lg text-gray-medium max-w-2xl mx-auto">
            {testimonials.subtitle}
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
          {testimonials.items.map((testimonial, index) => (
            <div
              key={index}
              className="bg-white p-6 rounded-xl shadow-md"
            >
              <div className="flex items-center gap-3 mb-4">
                {testimonial.avatar ? (
                  <Image
                    src={getAssetPath(testimonial.avatar)}
                    alt={testimonial.name}
                    width={48}
                    height={48}
                    className="rounded-full"
                  />
                ) : (
                  <div className="w-12 h-12 bg-accent-red rounded-full flex items-center justify-center text-white font-bold">
                    {testimonial.name.charAt(0)}
                  </div>
                )}
                <div>
                  <p className="font-semibold">{testimonial.name}</p>
                  <div className="flex items-center gap-1">
                    {[...Array(testimonial.rating)].map((_, i) => (
                      <FiStar key={i} className="text-yellow-400 fill-yellow-400" size={16} />
                    ))}
                  </div>
                </div>
              </div>
              <p className="text-gray-medium italic">"{testimonial.text}"</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
