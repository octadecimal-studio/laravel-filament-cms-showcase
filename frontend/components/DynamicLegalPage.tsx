'use client';

import { useEffect, useState } from 'react';

const TENANT_ID = process.env.NEXT_PUBLIC_TENANT_ID || '';
const FETCH_OPTS: RequestInit = { cache: 'no-store', headers: { Accept: 'application/json' } };

interface DynamicLegalPageProps {
  /** 'regulaminContent' or 'politykaContent' — field name from site-setting API */
  field: 'regulamin_content' | 'polityka_prywatnosci_content';
  initialHtml: string;
  className?: string;
}

export default function DynamicLegalPage({ field, initialHtml, className }: DynamicLegalPageProps) {
  const [html, setHtml] = useState(initialHtml);

  useEffect(() => {
    let cancelled = false;

    async function refresh() {
      try {
        const sp = new URLSearchParams();
        if (TENANT_ID) sp.set('tenant_id', TENANT_ID);
        sp.set('_t', String(Date.now()));
        const res = await fetch(`/api/motorent/site-setting?${sp}`, FETCH_OPTS);
        if (!res.ok || cancelled) return;
        const json = await res.json();
        const freshHtml = json.data?.[field];
        if (!cancelled && freshHtml) setHtml(freshHtml);
      } catch {
        /* keep initial data */
      }
    }

    refresh();
    return () => { cancelled = true; };
  }, [field]);

  return (
    <div
      className={className}
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}
