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
  { href: "/olt", label: "OLT", roles: ["admin", "dg", "dt", "supervisor", "coordinator"] },
  { href: "/equipes", label: "Équipes", roles: ["admin", "dg", "dt", "coordinator"] },
  { href: "/importer", label: "Importer", roles: ["admin"] },
  { href: "/utilisateurs", label: "Utilisateurs", roles: ["admin", "dg", "dt"] },
];

export function navFor(role: Role): NavItem[] {
  return NAV_ITEMS.filter((item) => !item.roles || item.roles.includes(role));
}

export function isActive(pathname: string, href: string): boolean {
  return href === "/" ? pathname === "/" : pathname === href || pathname.startsWith(`${href}/`);
}
