"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { bottomBarFor, isActive } from "@/lib/navigation";
import type { Role } from "@/lib/types";

/** Barre de navigation du téléphone : 4 entrées au plus, puis « Plus » qui ouvre le menu complet. */
export function BottomBar({ role, pathname }: { role: Role; pathname: string }) {
  const { main, all, hasMore } = bottomBarFor(role);
  const [open, setOpen] = useState(false);
  const closeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;
    closeRef.current?.focus();
    const onKey = (event: KeyboardEvent) => event.key === "Escape" && setOpen(false);
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [open]);

  const linkClass = (active: boolean) =>
    `flex h-14 min-w-0 flex-1 items-center justify-center px-1 text-center text-sm font-medium ${active ? "text-slate-900 underline underline-offset-4" : "text-zinc-700"}`;
  const hiddenActive = hasMore && all.some((item) => isActive(pathname, item.href) && !main.includes(item));

  return (
    <>
      <nav aria-label="Menu principal" className="fixed inset-x-0 bottom-0 z-10 flex border-t border-zinc-200 bg-white md:hidden">
        {main.map((item) => (
          <Link key={item.href} href={item.href} aria-current={isActive(pathname, item.href) ? "page" : undefined} className={linkClass(isActive(pathname, item.href))}>
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
          <div role="dialog" aria-modal="true" aria-label="Menu complet" className="absolute inset-x-0 bottom-0 space-y-2 rounded-t-2xl bg-white p-4 pb-6">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold">Menu</h2>
              <button ref={closeRef} onClick={() => setOpen(false)} className="h-11 rounded-lg border border-zinc-300 px-4 text-sm font-medium">
                Fermer
              </button>
            </div>
            <ul className="space-y-1">
              {all.map((item) => (
                <li key={item.href}>
                  <Link
                    href={item.href}
                    onClick={() => setOpen(false)}
                    aria-current={isActive(pathname, item.href) ? "page" : undefined}
                    className={`flex h-12 items-center rounded-lg px-3 text-base font-medium ${isActive(pathname, item.href) ? "bg-slate-900 text-white" : "text-zinc-900"}`}
                  >
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}
    </>
  );
}
