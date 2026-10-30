"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { Menu } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { BottomBar } from "@/components/bottom-bar";
import { MobileDrawer } from "@/components/mobile-drawer";
import { useAuth } from "@/lib/auth/auth-context";
import { groupsFor, HOME, isActive, isGroupActive } from "@/lib/navigation";

/** Mise en page connectée : barre latérale sur PC et tablette, barre du bas sur téléphone. */
export function AppShell({ children }: { children: React.ReactNode }) {
  const { user, loading, logout } = useAuth();
  const router = useRouter();
  const pathname = usePathname();
  const [menuOpen, setMenuOpen] = useState(false);
  const menuButtonRef = useRef<HTMLButtonElement>(null);
  const closeMenu = useCallback(() => {
    setMenuOpen(false);
    menuButtonRef.current?.focus();
  }, []);

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

  const groups = groupsFor(user.role);
  const linkClass = (active: boolean) => `flex h-11 items-center rounded-lg px-3 text-sm font-medium ${active ? "bg-slate-900 text-white" : "text-zinc-700 hover:bg-zinc-100"}`;

  return (
    <div className="flex min-h-screen flex-1 flex-col md:flex-row">
      <aside className="hidden w-56 shrink-0 flex-col border-r border-zinc-200 bg-white md:flex">
        <div className="p-4 text-lg font-semibold">FTTH Gestion</div>
        <nav aria-label="Menu principal" className="flex flex-1 flex-col gap-4 overflow-y-auto p-2">
          <Link href={HOME.href} aria-current={isActive(pathname, HOME.href) ? "page" : undefined} className={linkClass(isActive(pathname, HOME.href))}>
            {HOME.label}
          </Link>
          {groups.map((group) => (
            <div key={group.href} className="space-y-1">
              <Link href={group.href} className={`block px-3 text-xs font-semibold uppercase tracking-wide ${isGroupActive(pathname, group) ? "text-slate-900" : "text-zinc-700"}`}>
                {group.label}
              </Link>
              {group.items.map((item) => (
                <Link key={item.href} href={item.href} aria-current={isActive(pathname, item.href) ? "page" : undefined} className={linkClass(isActive(pathname, item.href))}>
                  {item.label}
                </Link>
              ))}
            </div>
          ))}
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between gap-3 border-b border-zinc-200 bg-white px-4 py-2 sm:px-6">
          <span className="flex items-center gap-2 md:hidden">
            <button
              ref={menuButtonRef}
              onClick={() => setMenuOpen(true)}
              aria-label="Ouvrir le menu"
              aria-haspopup="dialog"
              aria-expanded={menuOpen}
              aria-controls="menu-mobile"
              className="flex h-11 w-11 items-center justify-center rounded-lg border border-zinc-300"
            >
              <Menu aria-hidden="true" className="h-5 w-5" />
            </button>
            <span className="font-semibold">FTTH Gestion</span>
          </span>
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
      <MobileDrawer
        open={menuOpen}
        onClose={closeMenu}
        groups={groups}
        pathname={pathname}
        name={user.name}
        roleLabel={user.role_label}
        onLogout={async () => {
          await logout();
          router.replace("/login");
        }}
      />
    </div>
  );
}
