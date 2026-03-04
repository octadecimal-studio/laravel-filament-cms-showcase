import DynamicContent from '@/components/DynamicContent';
import { getAllContent, getMotorcycles } from '@/lib/api';

// ISR: regenerate every 30 seconds
export const revalidate = 30;

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
