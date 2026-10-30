"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { bottomBarFor, HOME, isActive, isGroupActive } from "@/lib/navigation";
import type { Role } from "@/lib/types";

/**
 * Barre de navigation du téléphone : l'accueil et les activités (4 au plus), puis « Plus » qui ouvre
 * le menu complet. Chaque activité ouvre sa page d'accueil, qui liste ses écrans.
 */
export function BottomBar({ role, pathname }: { role: Role; pathname: string }) {
  const { main, groups, hasMore } = bottomBarFor(role);
  const [open, setOpen] = useState(false);
  const closeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;
    closeRef.current?.focus();
    const onKey = (event: KeyboardEvent) => event.key === "Escape" && setOpen(false);
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [open]);

  const activeFor = (href: string) => {
    const group = groups.find((g) => g.href === href);
    return group ? isGroupActive(pathname, group) : isActive(pathname, href);
  };
  const linkClass = (active: boolean) =>
    `flex h-14 min-w-0 flex-1 items-center justify-center px-1 text-center text-sm font-medium ${active ? "text-slate-900 underline underline-offset-4" : "text-zinc-700"}`;
  const hiddenActive = hasMore && groups.some((g) => isGroupActive(pathname, g) && !main.some((m) => m.href === g.href));

  return (
    <>
      <nav aria-label="Menu principal" className="fixed inset-x-0 bottom-0 z-10 flex border-t border-zinc-200 bg-white md:hidden">
        {main.map((item) => (
          <Link key={item.href} href={item.href} aria-current={activeFor(item.href) ? "page" : undefined} className={linkClass(activeFor(item.href))}>
            {item.label}
          </Link>
        ))}
        {hasMore && (
          <button onClick={() => setOpen(true)} aria-haspopup="dialog" aria-expanded={open} className={linkClass(hiddenActive)}>
            Plus
          </button>
        )}
      </nav>

      {open && (
        <div className="fixed inset-0 z-20 md:hidden">
          <button aria-label="Fermer le menu" onClick={() => setOpen(false)} className="absolute inset-0 bg-black/40" />
          <div role="dialog" aria-modal="true" aria-label="Menu complet" className="absolute inset-x-0 bottom-0 max-h-[85vh] space-y-3 overflow-y-auto rounded-t-2xl bg-white p-4 pb-6">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold">Menu</h2>
              <button ref={closeRef} onClick={() => setOpen(false)} className="h-11 rounded-lg border border-zinc-300 px-4 text-sm font-medium">
                Fermer
              </button>
            </div>
            {[{ href: HOME.href, label: HOME.label, items: [] as { href: string; label: string }[] }, ...groups].map((group) => (
              <div key={group.href} className="space-y-1">
                <Link
                  href={group.href}
                  onClick={() => setOpen(false)}
                  className={`flex min-h-12 items-center rounded-lg px-3 text-base font-semibold ${isActive(pathname, group.href) ? "bg-slate-900 text-white" : "text-zinc-900"}`}
                >
                  {group.label}
                </Link>
                {group.items.map((item) => (
                  <Link
                    key={item.href}
                    href={item.href}
                    onClick={() => setOpen(false)}
                    aria-current={isActive(pathname, item.href) ? "page" : undefined}
                    className={`ml-3 flex min-h-12 items-center rounded-lg px-3 text-base ${isActive(pathname, item.href) ? "bg-slate-900 text-white" : "text-zinc-800"}`}
                  >
                    {item.label}
                  </Link>
                ))}
              </div>
            ))}
          </div>
        </div>
      )}
    </>
  );
}
