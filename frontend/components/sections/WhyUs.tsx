import Image from 'next/image';
import { getAssetPath } from '@/lib/paths';
import type { WhyUsData } from '@/lib/api';

interface WhyUsProps {
  whyUs: WhyUsData;
}

export default function WhyUs({ whyUs }: WhyUsProps) {
  return (
    <section id="o-nas" className="py-20 bg-gray-light">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
            {whyUs.title}
          </h2>
          <p className="text-lg text-gray-medium max-w-2xl mx-auto">
            {whyUs.subtitle}
          </p>
        </div>

        {/* Treść "O nas" z CMS */}
        {whyUs.aboutUsContent && (
          <div className="max-w-4xl mx-auto mb-16 bg-white p-8 md:p-12 rounded-2xl shadow-lg">
            <div
              className="prose prose-lg max-w-none prose-headings:font-heading prose-headings:text-primary-black prose-p:text-gray-700 prose-strong:text-accent-red prose-ul:list-disc prose-ul:pl-6 prose-li:text-gray-700 prose-blockquote:border-l-accent-red prose-blockquote:bg-gray-50 prose-blockquote:py-2 prose-blockquote:px-4 prose-blockquote:italic"
              dangerouslySetInnerHTML={{ __html: whyUs.aboutUsContent }}
            />
          </div>
        )}

        {/* Features grid */}
        {whyUs.features.length > 0 && (
          <div className={`grid grid-cols-1 ${whyUs.features.length === 3 ? 'md:grid-cols-3' : 'md:grid-cols-2 lg:grid-cols-3'} gap-8 max-w-5xl mx-auto`}>
            {whyUs.features.map((feature, index) => (
              <div
                key={index}
                className="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow text-center"
              >
                {feature.icon ? (
                  <div className="w-16 h-16 mb-4 flex items-center justify-center mx-auto">
                    <Image
                      src={feature.icon.startsWith('http') ? feature.icon : getAssetPath(feature.icon)}
                      alt={feature.title}
                      width={64}
                      height={64}
                      className="w-full h-full"
                      unoptimized={feature.icon.startsWith('http')}
                    />
                  </div>
                ) : (
                  <div className="w-16 h-16 bg-accent-red rounded-full flex items-center justify-center mx-auto mb-4">
                    <span className="text-white text-2xl">⚙</span>
                  </div>
                )}
                <h3 className="font-heading text-xl font-bold mb-2">{feature.title}</h3>
                <p className="text-gray-medium">{feature.description}</p>
              </div>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
