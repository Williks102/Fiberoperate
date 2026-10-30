"use client";

import Link from "next/link";
import { NavIcon } from "@/components/nav-icons";
import { bottomBarFor, isActive, isGroupActive } from "@/lib/navigation";
import type { Role } from "@/lib/types";

/**
 * Barre de navigation du téléphone : des icônes (accueil et activités), 5 au plus.
 * Le libellé reste accessible (lecteurs d'écran, infobulle) ; le menu complet est dans le tiroir hamburger.
 */
export function BottomBar({ role, pathname }: { role: Role; pathname: string }) {
  const { main, groups } = bottomBarFor(role);

  const active = (href: string) => {
    const group = groups.find((g) => g.href === href);
    return group ? isGroupActive(pathname, group) : isActive(pathname, href);
  };

  return (
    <nav aria-label="Menu principal" className="fixed inset-x-0 bottom-0 z-10 flex border-t border-zinc-200 bg-white md:hidden">
      {main.map((entry) => (
        <Link
          key={entry.href}
          href={entry.href}
          aria-label={entry.label}
          title={entry.label}
          aria-current={active(entry.href) ? "page" : undefined}
          className="flex h-14 min-w-0 flex-1 items-center justify-center"
        >
          <span className={`flex h-10 w-14 items-center justify-center rounded-full ${active(entry.href) ? "bg-slate-900 text-white" : "text-zinc-700"}`}>
            <NavIcon name={entry.icon} />
          </span>
        </Link>
      ))}
    </nav>
  );
}
