import DynamicContent from '@/components/DynamicContent';
import { getAllContent, getMotorcycles } from '@/lib/api';

// ISR: regenerate every 60 seconds
export const revalidate = 60;

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
