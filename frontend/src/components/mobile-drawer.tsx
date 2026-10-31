"use client";

import Link from "next/link";
import { LogOut, X } from "lucide-react";
import { useEffect, useRef } from "react";
import { BrandName } from "@/components/brand-mark";
import { ItemIcon, NavIcon } from "@/components/nav-icons";
import { HOME, isActive, type NavGroup } from "@/lib/navigation";

const FOCUSABLE = 'a[href], button:not([disabled])';

/** Tiroir de menu du téléphone (bouton hamburger) : tout le menu, groupé par activité, et la déconnexion. */
export function MobileDrawer({
  open,
  onClose,
  groups,
  pathname,
  name,
  roleLabel,
  onLogout,
}: {
  open: boolean;
  onClose: () => void;
  groups: NavGroup[];
  pathname: string;
  name: string;
  roleLabel: string;
  onLogout: () => void;
}) {
  const panelRef = useRef<HTMLDivElement>(null);
  const closeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;

    closeRef.current?.focus();
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") return onClose();
      if (event.key !== "Tab" || !panelRef.current) return;
      // Le focus reste dans le tiroir tant qu'il est ouvert.
      const items = [...panelRef.current.querySelectorAll<HTMLElement>(FOCUSABLE)];
      const first = items[0];
      const last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    // Si l'écran s'élargit (rotation), la barre latérale prend le relais : on ferme le tiroir.
    const wide = window.matchMedia("(min-width: 768px)");
    const onResize = () => wide.matches && onClose();

    document.addEventListener("keydown", onKey);
    wide.addEventListener("change", onResize);
    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener("keydown", onKey);
      wide.removeEventListener("change", onResize);
    };
  }, [open, onClose]);

  if (!open) return null;

  const linkClass = (active: boolean, indent = false) =>
    `flex min-h-12 items-center gap-3 rounded-lg px-3 text-base ${indent ? "ml-6" : "font-semibold"} ${active ? "bg-primary-soft text-primary-ink shadow-[inset_3px_0_0_var(--color-primary)]" : "text-ink hover:bg-slate-100"}`;

  return (
    <div className="fixed inset-0 z-30 md:hidden">
      <button aria-label="Fermer le menu" onClick={onClose} tabIndex={-1} className="drawer-backdrop absolute inset-0 bg-black/40" />
      <div
        ref={panelRef}
        id="menu-mobile"
        role="dialog"
        aria-modal="true"
        aria-label="Menu"
        className="drawer-panel absolute inset-y-0 left-0 flex w-[85%] max-w-xs flex-col gap-3 overflow-y-auto bg-white p-4"
      >
        <div className="flex items-center justify-between gap-3">
          <BrandName />
          <button ref={closeRef} onClick={onClose} aria-label="Fermer le menu" className="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-300">
            <X aria-hidden="true" className="h-5 w-5" />
          </button>
        </div>
        <div className="rounded-xl bg-slate-50 px-3 py-2">
          <p className="font-semibold">{name}</p>
          <p className="text-sm text-slate-700">{roleLabel}</p>
        </div>

        <nav aria-label="Menu complet" className="flex-1 space-y-2">
          <Link href={HOME.href} onClick={onClose} aria-current={isActive(pathname, HOME.href) ? "page" : undefined} className={linkClass(isActive(pathname, HOME.href))}>
            <NavIcon name="home" className="h-5 w-5" />
            {HOME.label}
          </Link>
          {groups.map((group) => (
            <div key={group.href} className="space-y-1">
              <Link href={group.href} onClick={onClose} aria-current={isActive(pathname, group.href) ? "page" : undefined} className={linkClass(isActive(pathname, group.href))}>
                <NavIcon name={group.icon} className="h-5 w-5" />
                {group.label}
              </Link>
              {group.items.map((item) => (
                <Link key={item.href} href={item.href} onClick={onClose} aria-current={isActive(pathname, item.href) ? "page" : undefined} className={linkClass(isActive(pathname, item.href), true)}>
                  <ItemIcon href={item.href} />
                  {item.label}
                </Link>
              ))}
            </div>
          ))}
        </nav>

        <button onClick={onLogout} className="flex h-12 items-center justify-center gap-2 rounded-xl border border-slate-300 text-base font-medium">
          <LogOut aria-hidden="true" className="h-5 w-5" />
          Se déconnecter
        </button>
      </div>
    </div>
  );
}
