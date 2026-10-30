import type { Metadata, Viewport } from "next";
import { AuthProvider } from "@/lib/auth/auth-context";
import { ServiceWorkerRegister } from "@/components/service-worker-register";
import "./globals.css";

export const metadata: Metadata = {
  title: "FTTH Gestion",
  description: "Gestion des déploiements et raccordements fibre optique",
  // Thème clair uniquement : empêche aussi l'assombrissement automatique de certains navigateurs mobiles.
  other: { "color-scheme": "light" },
};

export const viewport: Viewport = {
  themeColor: "#0f172a",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="fr" className="h-full antialiased">
      <body className="min-h-full flex flex-col">
        <AuthProvider>{children}</AuthProvider>
        <ServiceWorkerRegister />
      </body>
    </html>
  );
}
