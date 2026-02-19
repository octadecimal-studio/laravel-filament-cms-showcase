'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { usePathname } from 'next/navigation';
import { FiMenu, FiX } from 'react-icons/fi';
import { getAssetPath, normalizeHashHref } from '@/lib/paths';
import type { SiteData, NavigationData } from '@/lib/api';

interface HeaderProps {
  site: SiteData;
  navigation: NavigationData;
}

export default function Header({ site, navigation }: HeaderProps) {
  const [isScrolled, setIsScrolled] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const pathname = usePathname();
  const isLightPage =
    pathname.startsWith('/motocykle/') ||
    pathname === '/regulamin' ||
    pathname === '/polityka-prywatnosci';

  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 50);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const useDarkHeader = isLightPage || isScrolled;
  const linkClass = useDarkHeader
    ? 'text-primary-black hover:text-accent-red'
    : 'text-white hover:text-accent-red';
  const logoClass = useDarkHeader ? 'text-primary-black' : 'text-white';
  const mobileBtnClass = useDarkHeader ? 'text-primary-black' : 'text-white';
  const mobileBorderClass = useDarkHeader ? 'border-gray-light' : 'border-white/20';
  const headerBg =
    isLightPage ? 'bg-white/95 backdrop-blur-sm shadow-md' : isScrolled ? 'bg-white/95 backdrop-blur-sm shadow-md' : 'bg-transparent';

  return (
    <header
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${headerBg}`}
    >
      <nav className="container mx-auto px-4 py-4">
        <div className="flex items-center justify-between">
          {/* Logo */}
          <Link href="/" className="flex items-center gap-3">
            {site.logo && (
              <Image
                src={site.logo.startsWith('http') ? site.logo : getAssetPath(site.logo)}
                alt="Logo"
                width={80}
                height={80}
                className="h-16 w-16 md:h-20 md:w-20 object-contain"
                unoptimized={site.logo.startsWith('http')}
              />
            )}
            <span className={`font-heading text-xl md:text-2xl font-bold transition-colors ${logoClass}`}>
              {site.name}
            </span>
          </Link>

          {/* Desktop Menu */}
          <div className="hidden md:flex items-center gap-8">
            {navigation.links.map((link) => (
              <Link
                key={link.href}
                href={normalizeHashHref(link.href)}
                className={`transition-colors ${linkClass}`}
              >
                {link.label}
              </Link>
            ))}
            {navigation.cta.href.startsWith('http') ? (
              <a
                href={navigation.cta.href}
                target="_blank"
                rel="noopener noreferrer"
                className={
                  navigation.cta.variant === 'outline'
                    ? `border-2 px-6 py-2 rounded-lg font-semibold transition-colors ${
                        isLightPage
                          ? 'border-primary-black text-primary-black hover:bg-primary-black hover:text-white'
                          : 'border-white text-white hover:bg-white hover:text-primary-black'
                      }`
                    : 'bg-accent-red text-white px-6 py-2 rounded-lg font-semibold hover:bg-red-700 transition-colors'
                }
              >
                {navigation.cta.label}
              </a>
            ) : (
              <Link
                href={normalizeHashHref(navigation.cta.href)}
                className={
                  navigation.cta.variant === 'outline'
                    ? `border-2 px-6 py-2 rounded-lg font-semibold transition-colors ${
                        isLightPage
                          ? 'border-primary-black text-primary-black hover:bg-primary-black hover:text-white'
                          : 'border-white text-white hover:bg-white hover:text-primary-black'
                      }`
                    : 'bg-accent-red text-white px-6 py-2 rounded-lg font-semibold hover:bg-red-700 transition-colors'
                }
              >
                {navigation.cta.label}
              </Link>
            )}
          </div>

          {/* Mobile Menu Button */}
          <button
            className={`md:hidden transition-colors ${mobileBtnClass}`}
            onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
          >
            {isMobileMenuOpen ? <FiX size={24} /> : <FiMenu size={24} />}
          </button>
        </div>

        {/* Mobile Menu */}
        {isMobileMenuOpen && (
          <div className={`md:hidden mt-4 pb-4 border-t ${mobileBorderClass}`}>
            <div className="flex flex-col gap-4 pt-4">
              {navigation.links.map((link) => (
                <Link
                  key={link.href}
                  href={normalizeHashHref(link.href)}
                  className={`transition-colors ${linkClass}`}
                  onClick={() => setIsMobileMenuOpen(false)}
                >
                  {link.label}
                </Link>
              ))}
              {navigation.cta.href.startsWith('http') ? (
                <a
                  href={navigation.cta.href}
                  target="_blank"
                  rel="noopener noreferrer"
                  className={
                    navigation.cta.variant === 'outline'
                      ? `border-2 px-6 py-2 rounded-lg font-semibold transition-colors text-center ${
                          isLightPage
                            ? 'border-primary-black text-primary-black hover:bg-primary-black hover:text-white'
                            : 'border-white text-white hover:bg-white hover:text-primary-black'
                        }`
                      : 'bg-accent-red text-white px-6 py-2 rounded-lg font-semibold hover:bg-red-700 transition-colors text-center'
                  }
                  onClick={() => setIsMobileMenuOpen(false)}
                >
                  {navigation.cta.label}
                </a>
              ) : (
                <Link
                  href={normalizeHashHref(navigation.cta.href)}
                  className={
                    navigation.cta.variant === 'outline'
                      ? `border-2 px-6 py-2 rounded-lg font-semibold transition-colors text-center ${
                          isLightPage
                            ? 'border-primary-black text-primary-black hover:bg-primary-black hover:text-white'
                            : 'border-white text-white hover:bg-white hover:text-primary-black'
                        }`
                      : 'bg-accent-red text-white px-6 py-2 rounded-lg font-semibold hover:bg-red-700 transition-colors text-center'
                  }
                  onClick={() => setIsMobileMenuOpen(false)}
                >
                  {navigation.cta.label}
                </Link>
              )}
            </div>
          </div>
        )}
      </nav>
    </header>
  );
}
