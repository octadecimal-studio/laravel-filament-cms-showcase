import DynamicContent from '@/components/DynamicContent';
import { getAllContent, getMotorcycles } from '@/lib/api';

// Force dynamic rendering - fetch fresh data on every request
export const dynamic = 'force-dynamic';
export const revalidate = 0;

export default async function Home() {
  const [content, motorcycles] = await Promise.all([
    getAllContent(),
    getMotorcycles({ slug: 'example-rental.test', per_page: 20 })
  ]);

  return (
    <DynamicContent
      initialContent={content}
      initialBikes={motorcycles.data}
      totalBikes={motorcycles.meta.total}
    />
  );
}
