import Link from 'next/link';
import Image from 'next/image';
import { getAssetPath } from '@/lib/paths';
import type { HeroData } from '@/lib/api';

interface HeroProps {
  hero: HeroData;
}

export default function Hero({ hero }: HeroProps) {
  return (
    <section className="relative min-h-screen flex items-center justify-center overflow-hidden">
      {/* Background Image */}
      <div className="absolute inset-0 z-0">
        <Image
          src={getAssetPath(hero.image)}
          alt="Hero Motorcycle"
          fill
          className="object-cover"
          priority
        />
        {/* Dark Overlay */}
        <div className="absolute inset-0 bg-gradient-to-b from-black/70 via-black/50 to-black/70" />
      </div>

      {/* Content */}
      <div className="relative z-10 container mx-auto px-4 text-center text-white">
        <h1 className="font-heading text-4xl md:text-6xl lg:text-7xl font-bold mb-6 leading-tight">
          {hero.title}
          <br />
          <span className="text-accent-red">{hero.titleHighlight}</span>
        </h1>
        {hero.description ? (
          <div className="text-lg md:text-xl mb-8 text-gray-200 max-w-3xl mx-auto whitespace-pre-line">
            {hero.description}
          </div>
        ) : (
          <p className="text-lg md:text-xl mb-8 text-gray-200 max-w-2xl mx-auto">
            {hero.subtitle}
          </p>
        )}
        <div className="flex flex-col sm:flex-row gap-4 justify-center items-center">
          {hero.buttons.map((button) => {
            const isExternal = button.href.startsWith('http');
            const className =
              button.variant === 'primary'
                ? 'bg-accent-red text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-red-700 transition-colors'
                : 'bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white hover:text-primary-black transition-colors';
            return isExternal ? (
              <a
                key={button.href}
                href={button.href}
                target="_blank"
                rel="noopener noreferrer"
                className={className}
              >
                {button.label}
              </a>
            ) : (
              <Link
                key={button.href}
                href={button.href}
                className={className}
              >
                {button.label}
              </Link>
            );
          })}
        </div>
      </div>
    </section>
  );
}
