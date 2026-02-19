'use client';

import { useState } from 'react';
import { FiChevronDown, FiChevronUp } from 'react-icons/fi';
import type { TermsData } from '@/lib/api';

interface TermsProps {
  terms: TermsData;
}

export default function Terms({ terms }: TermsProps) {
  const [openIndex, setOpenIndex] = useState<number | null>(0);

  return (
    <section className="py-20 bg-gray-light">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="font-heading text-4xl md:text-5xl font-bold mb-4">
            {terms.title}
          </h2>
        </div>

        <div className="max-w-3xl mx-auto">
          {terms.items.map((term, index) => (
            <div
              key={index}
              className="bg-white rounded-xl shadow-md mb-4 overflow-hidden"
            >
              <button
                onClick={() => setOpenIndex(openIndex === index ? null : index)}
                className="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-gray-light transition-colors"
              >
                <span className="font-heading text-lg font-bold">{term.title}</span>
                {openIndex === index ? (
                  <FiChevronUp className="text-accent-red" />
                ) : (
                  <FiChevronDown className="text-gray-medium" />
                )}
              </button>
              {openIndex === index && (
                <div className="px-6 pb-4 text-gray-medium">
                  {term.description ? (
                    <div
                      className="prose prose-sm max-w-none [&_ul]:list-disc [&_ul]:pl-5 [&_li]:mb-1"
                      dangerouslySetInnerHTML={{ __html: term.description }}
                    />
                  ) : (
                    <ul className="space-y-2">
                      {term.points.map((point, pointIndex) => (
                        <li key={pointIndex}>• {point}</li>
                      ))}
                    </ul>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
