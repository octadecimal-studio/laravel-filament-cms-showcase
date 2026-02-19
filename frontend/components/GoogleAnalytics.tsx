import Script from 'next/script';
import { getSiteData } from '@/lib/api';

export default async function GoogleAnalytics() {
  let gaCode: string | null = null;

  try {
    const siteData = await getSiteData();
    gaCode = siteData.googleAnalyticsCode || null;
  } catch {
    // Fallback: use nothing if API unavailable
  }

  if (!gaCode) return null;

  // Extract GA ID from code if it's a full script tag, or use as-is if it's just the ID
  const gaIdMatch = gaCode.match(/G-[A-Z0-9]+/);
  const gaId = gaIdMatch ? gaIdMatch[0] : null;

  if (gaId) {
    return (
      <>
        <Script
          src={`https://www.googletagmanager.com/gtag/js?id=${gaId}`}
          strategy="afterInteractive"
        />
        <Script id="google-analytics" strategy="afterInteractive">
          {`window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","${gaId}");`}
        </Script>
      </>
    );
  }

  // If it's a raw script snippet, inject directly
  return (
    <Script id="google-analytics-custom" strategy="afterInteractive">
      {gaCode}
    </Script>
  );
}
