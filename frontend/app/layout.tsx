import type { Metadata } from "next";
import GoogleAnalytics from "@/components/GoogleAnalytics";
import { GoogleTagManagerHead, GoogleTagManagerBody } from "@/components/GoogleTagManager";
import "./globals.css";

export const metadata: Metadata = {
  title: "Wypożyczalnia Motocykli | Najlepsze Modele | Profesjonalna Obsługa",
  description: "Wypożycz motocykl swoich marzeń. Najlepsze modele, najlepsze ceny, niezapomniane przeżycia. Pełne ubezpieczenie, profesjonalna obsługa.",
  keywords: "wypożyczalnia motocykli, motocykle, wynajem motocykli, Yamaha, BMW, Harley-Davidson",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="pl">
      <body>
        <GoogleTagManagerBody />
        <GoogleTagManagerHead />
        <GoogleAnalytics />
        {children}
      </body>
    </html>
  );
}
