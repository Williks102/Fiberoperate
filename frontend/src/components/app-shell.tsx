"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect } from "react";
import { BottomBar } from "@/components/bottom-bar";
import { useAuth } from "@/lib/auth/auth-context";
import { isActive, navFor } from "@/lib/navigation";

/** Mise en page connectée : barre latérale sur PC et tablette, barre du bas sur téléphone. */
export function AppShell({ children }: { children: React.ReactNode }) {
  const { user, loading, logout } = useAuth();
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    if (!loading && !user) router.replace("/login");
  }, [loading, user, router]);

  if (loading || !user) {
    return (
      <main className="flex flex-1 items-center justify-center p-6" aria-busy="true">
        <div className="h-6 w-40 animate-pulse rounded bg-zinc-200" />
      </main>
    );
  }

  const items = navFor(user.role);

  return (
    <div className="flex min-h-screen flex-1 flex-col md:flex-row">
      <aside className="hidden w-20 shrink-0 flex-col border-r border-zinc-200 bg-white md:flex lg:w-56">
        <div className="p-4 text-sm font-semibold lg:text-lg">FTTH Gestion</div>
        <nav aria-label="Menu principal" className="flex flex-1 flex-col gap-1 p-2">
          {items.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              aria-current={isActive(pathname, item.href) ? "page" : undefined}
              className={`flex h-11 items-center rounded-lg px-3 text-sm font-medium ${
                isActive(pathname, item.href) ? "bg-slate-900 text-white" : "text-zinc-700 hover:bg-zinc-100"
              }`}
            >
              {item.label}
            </Link>
          ))}
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between gap-3 border-b border-zinc-200 bg-white px-4 py-2 sm:px-6">
          <span className="font-semibold md:hidden">FTTH Gestion</span>
          <span className="hidden text-sm text-zinc-700 md:block">
            {user.name} · {user.role_label}
          </span>
          <button
            onClick={async () => {
              await logout();
              router.replace("/login");
            }}
            className="h-11 rounded-lg border border-zinc-300 px-4 text-sm font-medium"
          >
            Se déconnecter
          </button>
        </header>

        <main className="mx-auto w-full max-w-6xl flex-1 p-4 pb-24 sm:p-6 md:pb-6">{children}</main>
      </div>

      <BottomBar role={user.role} pathname={pathname} />
    </div>
  );
}
