import type { Role } from "@/lib/types";

export interface NavItem {
  href: string;
  label: string;
  /** Rôles qui voient l'entrée ; absent = tous. */
  roles?: Role[];
}

/**
 * Entrées du menu. On n'y ajoute un écran qu'une fois qu'il existe :
 * le menu grandit au fil des tranches livrées.
 */
export const NAV_ITEMS: NavItem[] = [
  { href: "/", label: "Accueil" },
  { href: "/reseau", label: "Réseau" },
  { href: "/plaques", label: "Plaques", roles: ["admin"] },
  { href: "/sous-plaques", label: "Sous-plaques", roles: ["admin"] },
  { href: "/olt", label: "OLT", roles: ["admin", "dg", "dt", "supervisor", "coordinator"] },
  { href: "/equipes", label: "Équipes", roles: ["admin", "dg", "dt", "coordinator"] },
  { href: "/importer", label: "Importer", roles: ["admin"] },
  { href: "/utilisateurs", label: "Utilisateurs", roles: ["admin", "dg", "dt"] },
];

export function navFor(role: Role): NavItem[] {
  return NAV_ITEMS.filter((item) => !item.roles || item.roles.includes(role));
}

/** Entrées de la barre du bas sur téléphone, par rôle (4 au plus ; le reste est dans « Plus »). */
export const BOTTOM_BAR: Record<Role, string[]> = {
  admin: ["/", "/reseau", "/utilisateurs", "/equipes"],
  dg: ["/", "/reseau", "/equipes", "/utilisateurs"],
  dt: ["/", "/reseau", "/equipes", "/utilisateurs"],
  supervisor: ["/", "/reseau", "/olt"],
  coordinator: ["/", "/reseau", "/equipes", "/olt"],
  technician: ["/", "/reseau"],
};

export const BOTTOM_BAR_MAX = 4;

/**
 * Découpage du menu pour le téléphone : `main` dans la barre, `all` dans le tiroir « Plus ».
 * Sans « Plus » quand tout le menu tient dans la barre.
 */
export function bottomBarFor(role: Role): { main: NavItem[]; all: NavItem[]; hasMore: boolean } {
  const all = navFor(role);
  const hasMore = all.length > BOTTOM_BAR_MAX;
  const main = hasMore ? BOTTOM_BAR[role].map((href) => all.find((item) => item.href === href)).filter((item): item is NavItem => item !== undefined).slice(0, BOTTOM_BAR_MAX) : all;

  return { main, all, hasMore };
}

export function isActive(pathname: string, href: string): boolean {
  return href === "/" ? pathname === "/" : pathname === href || pathname.startsWith(`${href}/`);
}
