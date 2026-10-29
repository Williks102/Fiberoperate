import { Suspense } from "react";
import { AppShell } from "@/components/app-shell";

export default function AppLayout({ children }: { children: React.ReactNode }) {
  // Suspense requis par Next.js pour les routes dynamiques (/utilisateurs/[id]) qui lisent l'URL.
  return (
    <Suspense fallback={<div aria-busy="true" className="flex-1" />}>
      <AppShell>{children}</AppShell>
    </Suspense>
  );
}
