import { notFound } from 'next/navigation';
import { getMotorcycleBySlug, getAllContent } from '@/lib/api';
import MotorcycleDetailClient from './MotorcycleDetailClient';
import mockMotorcycles from '@/data/mock-motorcycles.json';

// Force dynamic rendering - fetch fresh data on every request
export const dynamic = 'force-dynamic';
export const revalidate = 0;
export const dynamicParams = true;

const FALLBACK_SLUGS = (mockMotorcycles.data as { slug: string }[]).map((m) => ({ slug: m.slug }));

export async function generateStaticParams() {
  try {
    const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api.example.test/api/motorent';
    const TENANT_ID = process.env.NEXT_PUBLIC_TENANT_ID || process.env.TENANT_ID || 'a0e1ef09-91b0-476a-aec1-45ae89c36bd4';
    
    const response = await fetch(`${API_BASE_URL}/motorcycles?tenant_id=${TENANT_ID}&per_page=100`, {
      cache: 'no-store',
    });
    
    if (!response.ok) {
      console.warn('Failed to fetch motorcycles for static params, using fallback slugs');
      return FALLBACK_SLUGS;
    }
    
    const data = await response.json();
    const motorcycles = data.data || [];
    
    if (motorcycles.length === 0) return FALLBACK_SLUGS;
    
    return motorcycles.map((moto: { slug: string }) => ({
      slug: moto.slug,
    }));
  } catch (error) {
    console.error('Error generating static params:', error);
    return FALLBACK_SLUGS;
  }
}

export default async function MotorcycleDetailPage({ 
  params 
}: { 
  params: Promise<{ slug: string }> | { slug: string }
}) {
  const resolvedParams = params instanceof Promise ? await params : params;
  const slug = resolvedParams.slug;
  
  const [motorcycle, content] = await Promise.all([
    getMotorcycleBySlug(slug),
    getAllContent(),
  ]);
  
  if (!motorcycle) {
    notFound();
  }
  
  return (
    <MotorcycleDetailClient
      motorcycle={motorcycle}
      site={content.site}
      navigation={content.navigation}
      footer={content.footer}
      contact={content.contact}
    />
  );
}
