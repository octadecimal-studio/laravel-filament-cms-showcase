import { FiCalendar, FiFileText, FiKey } from 'react-icons/fi';
import type { HowItWorksData } from '@/lib/api';

interface HowItWorksProps {
  howItWorks: HowItWorksData;
}

const iconMap: Record<number, typeof FiCalendar> = {
  1: FiCalendar,
  2: FiFileText,
  3: FiKey,
  4: FiKey, // fallback
};

export default function HowItWorks({ howItWorks }: HowItWorksProps) {
  return (
    <section className="py-20 bg-gray-light">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
            {howItWorks.title}
          </h2>
          <p className="text-lg text-gray-medium max-w-2xl mx-auto">
            {howItWorks.subtitle}
          </p>
        </div>

        <div className={`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-6xl mx-auto`}>
          {howItWorks.steps.map((step, index) => {
            const Icon = iconMap[step.number] || FiCalendar;
            return (
              <div key={index} className="text-center bg-white p-6 rounded-xl shadow-md">
                <div className="w-20 h-20 bg-accent-red rounded-full flex items-center justify-center mx-auto mb-4">
                  <Icon className="text-white text-3xl" />
                </div>
                <div className="text-3xl font-bold text-accent-red mb-2">{step.number}</div>
                <h3 className="font-heading text-xl font-bold mb-2">{step.title}</h3>
                <p className="text-gray-medium">{step.description}</p>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
