"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { Menu } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { AccountMenu } from "@/components/account-menu";
import { BottomBar } from "@/components/bottom-bar";
import { BrandName } from "@/components/brand-mark";
import { ItemIcon, NavIcon } from "@/components/nav-icons";
import { NotificationBell } from "@/components/notification-bell";
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
        <div className="h-6 w-40 animate-pulse rounded bg-slate-200" />
      </main>
    );
  }

  const groups = groupsFor(user.role, user.skills);
  const logoutAndLeave = async () => {
    await logout();
    router.replace("/login");
  };
  // Entrée active : fond bleu clair, repère à gauche ; le texte seul ne porte jamais l'information (icône + libellé + état).
  const linkClass = (active: boolean) =>
    `flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium ${active ? "bg-primary-soft text-primary-ink shadow-[inset_3px_0_0_var(--color-primary)]" : "text-slate-700 hover:bg-slate-100"}`;

  return (
    <div className="flex min-h-screen flex-1 flex-col md:flex-row">
      <aside className="hidden w-60 shrink-0 flex-col border-r border-slate-200 bg-white md:flex">
        <div className="flex h-16 items-center border-b border-slate-100 px-4">
          <BrandName />
        </div>
        <nav aria-label="Menu principal" className="flex flex-1 flex-col gap-2 overflow-y-auto p-3">
          <Link href={HOME.href} aria-current={isActive(pathname, HOME.href) ? "page" : undefined} className={linkClass(isActive(pathname, HOME.href))}>
            <NavIcon name="home" className="h-5 w-5" />
            {HOME.label}
          </Link>
          {groups.map((group) => (
            <div key={group.href} className="space-y-1 pt-2">
              <Link href={group.href} className={`flex h-9 items-center px-3 text-xs font-semibold uppercase tracking-wider ${isGroupActive(pathname, group) ? "text-primary-ink" : "text-slate-600"}`}>
                {group.label}
              </Link>
              {group.items.map((item) => (
                <Link key={item.href} href={item.href} aria-current={isActive(pathname, item.href) ? "page" : undefined} className={linkClass(isActive(pathname, item.href))}>
                  <ItemIcon href={item.href} />
                  {item.label}
                </Link>
              ))}
            </div>
          ))}
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6">
          <span className="flex items-center gap-3 md:hidden">
            <button
              ref={menuButtonRef}
              onClick={() => setMenuOpen(true)}
              aria-label="Ouvrir le menu"
              aria-haspopup="dialog"
              aria-expanded={menuOpen}
              aria-controls="menu-mobile"
              className="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-300 bg-white"
            >
              <Menu aria-hidden="true" className="h-5 w-5" />
            </button>
            <BrandName />
          </span>
          <span className="hidden md:block" />
          <span className="flex items-center gap-2">
            <NotificationBell />
            <AccountMenu name={user.name} email={user.email} roleLabel={user.role_label} onLogout={logoutAndLeave} />
          </span>
        </header>

        <main className="mx-auto w-full max-w-6xl flex-1 p-4 pb-24 sm:p-6 md:pb-6">{children}</main>
      </div>

      <BottomBar role={user.role} skills={user.skills} pathname={pathname} />
      <MobileDrawer
        open={menuOpen}
        onClose={closeMenu}
        groups={groups}
        pathname={pathname}
        name={user.name}
        roleLabel={user.role_label}
        onLogout={logoutAndLeave}
      />
    </div>
  );
}
