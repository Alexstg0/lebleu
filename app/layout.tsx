import "./globals.css";
import type { Metadata, Viewport } from "next";
import Pwa from "./Pwa";

export const metadata: Metadata = {
  title: "Le Bleu — Control de Embarcación",
  description: "Control financiero y operativo de la embarcación Le Bleu",
  manifest: "/manifest.webmanifest",
  appleWebApp: { capable: true, statusBarStyle: "black-translucent", title: "Le Bleu" },
  icons: {
    icon: "/icon-192.png",
    apple: "/apple-touch-icon.png",
  },
};

export const viewport: Viewport = {
  themeColor: "#1a3a5c",
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="es">
      <body>
        {children}
        <Pwa />
      </body>
    </html>
  );
}
