'use client';

import { useState } from 'react';
import { FiX, FiCalendar, FiPhone, FiMessageCircle } from 'react-icons/fi';
import type { FooterData, ContactData, ReservationSettings } from '@/lib/api';
import { MONDAY_RESERVATION_FORM_URL } from '@/lib/paths';

interface FloatingActionsProps {
  footer: FooterData;
  contact: ContactData;
  reservationSettings?: ReservationSettings;
}

export default function FloatingActions({ footer, contact, reservationSettings }: FloatingActionsProps) {
  const [isOpen, setIsOpen] = useState(false);
  const formType = reservationSettings?.formType || 'external';
  const externalUrl = reservationSettings?.externalUrl || MONDAY_RESERVATION_FORM_URL;
  const social = footer.socialMedia;

  return (
    <div className="fixed bottom-6 right-6 z-40 flex flex-col items-end gap-3">
      {/* Expanded menu */}
      {isOpen && (
        <div className="flex flex-col gap-2 mb-2 animate-in fade-in slide-in-from-bottom-2">
          {/* Reservation */}
          {formType === 'external' && externalUrl ? (
            <a
              href={externalUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-3 bg-white shadow-lg rounded-full pl-4 pr-5 py-3 hover:shadow-xl transition-shadow group"
            >
              <span className="w-10 h-10 bg-accent-red rounded-full flex items-center justify-center text-white shrink-0">
                <FiCalendar size={20} />
              </span>
              <span className="font-semibold text-sm text-gray-dark group-hover:text-accent-red transition-colors whitespace-nowrap">
                Rezerwuj motocykl
              </span>
            </a>
          ) : (
            <a
              href="/#rezerwacja"
              onClick={(e) => { e.preventDefault(); setIsOpen(false); document.getElementById('rezerwacja')?.scrollIntoView({ behavior: 'smooth' }); }}
              className="flex items-center gap-3 bg-white shadow-lg rounded-full pl-4 pr-5 py-3 hover:shadow-xl transition-shadow group"
            >
              <span className="w-10 h-10 bg-accent-red rounded-full flex items-center justify-center text-white shrink-0">
                <FiCalendar size={20} />
              </span>
              <span className="font-semibold text-sm text-gray-dark group-hover:text-accent-red transition-colors whitespace-nowrap">
                Rezerwuj motocykl
              </span>
            </a>
          )}

          {/* Phone buttons */}
          {(contact.phones && contact.phones.length > 0
            ? contact.phones
            : [{ label: '', number: contact.phone }]
          ).map((p, i) => (
            <a
              key={i}
              href={`tel:${p.number.replace(/\s/g, '')}`}
              className="flex items-center gap-3 bg-white shadow-lg rounded-full pl-4 pr-5 py-3 hover:shadow-xl transition-shadow group"
            >
              <span className="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center text-white shrink-0">
                <FiPhone size={20} />
              </span>
              <span className="font-semibold text-sm text-gray-dark group-hover:text-green-600 transition-colors whitespace-nowrap">
                {p.label ? `${p.label}: ${p.number}` : p.number}
              </span>
            </a>
          ))}

          {/* WhatsApp */}
          {contact.whatsapp?.map((w, i) => (
            <a
              key={`wa-${i}`}
              href={`https://wa.me/${w.number.replace(/[^0-9+]/g, '')}`}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-3 bg-white shadow-lg rounded-full pl-4 pr-5 py-3 hover:shadow-xl transition-shadow group"
            >
              <span className="w-10 h-10 bg-[#25D366] rounded-full flex items-center justify-center text-white shrink-0">
                <FiMessageCircle size={20} />
              </span>
              <span className="font-semibold text-sm text-gray-dark group-hover:text-[#25D366] transition-colors whitespace-nowrap">
                WhatsApp: {w.label}
              </span>
            </a>
          ))}

          {/* Facebook */}
          {social?.facebook && (
            <a
              href={social.facebook}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-3 bg-white shadow-lg rounded-full pl-4 pr-5 py-3 hover:shadow-xl transition-shadow group"
            >
              <span className="w-10 h-10 bg-[#1877F2] rounded-full flex items-center justify-center text-white shrink-0">
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
              </span>
              <span className="font-semibold text-sm text-gray-dark group-hover:text-[#1877F2] transition-colors whitespace-nowrap">
                Facebook
              </span>
            </a>
          )}

          {/* Instagram */}
          {social?.instagram && (
            <a
              href={social.instagram}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-3 bg-white shadow-lg rounded-full pl-4 pr-5 py-3 hover:shadow-xl transition-shadow group"
            >
              <span className="w-10 h-10 bg-gradient-to-tr from-yellow-500 via-pink-500 to-purple-600 rounded-full flex items-center justify-center text-white shrink-0">
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
              </span>
              <span className="font-semibold text-sm text-gray-dark group-hover:text-pink-500 transition-colors whitespace-nowrap">
                Instagram
              </span>
            </a>
          )}

          {/* TikTok */}
          {social?.tiktok && (
            <a
              href={social.tiktok}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-3 bg-white shadow-lg rounded-full pl-4 pr-5 py-3 hover:shadow-xl transition-shadow group"
            >
              <span className="w-10 h-10 bg-black rounded-full flex items-center justify-center text-white shrink-0">
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 0 0-.79-.05A6.34 6.34 0 0 0 3.15 15.2a6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.34-6.34V9.11a8.16 8.16 0 0 0 4.76 1.53v-3.4a4.85 4.85 0 0 1-1-.55z"/></svg>
              </span>
              <span className="font-semibold text-sm text-gray-dark group-hover:text-black transition-colors whitespace-nowrap">
                TikTok
              </span>
            </a>
          )}
        </div>
      )}

      {/* Main FAB button */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className={`w-16 h-16 rounded-full shadow-lg flex items-center justify-center transition-all duration-300 ${
          isOpen
            ? 'bg-gray-800 rotate-0'
            : 'bg-accent-red hover:bg-red-700 animate-pulse hover:animate-none'
        }`}
        aria-label={isOpen ? 'Zamknij menu' : 'Otwórz menu kontaktowe'}
      >
        {isOpen ? (
          <FiX size={28} className="text-white" />
        ) : (
          <FiCalendar size={28} className="text-white" />
        )}
      </button>
    </div>
  );
}
