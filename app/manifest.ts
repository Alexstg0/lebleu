import type { MetadataRoute } from "next";

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "Le Bleu — Control de embarcación",
    short_name: "Le Bleu",
    description: "Control de la embarcación Le Bleu: estado de cuenta, calendario, bitácora.",
    start_url: "/",
    display: "standalone",
    orientation: "portrait",
    background_color: "#1a3a5c",
    theme_color: "#1a3a5c",
    lang: "es",
    icons: [
      { src: "/icon-192.png", sizes: "192x192", type: "image/png", purpose: "any" },
      { src: "/icon-512.png", sizes: "512x512", type: "image/png", purpose: "any" },
      { src: "/icon-512.png", sizes: "512x512", type: "image/png", purpose: "maskable" },
    ],
  };
}
