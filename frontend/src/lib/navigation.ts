import type { Role } from "@/lib/types";

export interface NavItem {
  href: string;
  label: string;
  /** Une phrase affichée sur la page de l'activité. */
  description?: string;
  /** Rôles qui voient l'entrée ; absent = tous. */
  roles?: Role[];
}

/** Une activité du métier : ses écrans sont regroupés sous une page d'accueil (« hub »). */
export interface NavGroup {
  /** Page d'accueil de l'activité. */
  href: string;
  label: string;
  description: string;
  items: NavItem[];
}

export const HOME: NavItem = { href: "/", label: "Accueil" };

/**
 * Menu par activité. On n'y ajoute un écran qu'une fois qu'il existe : une activité sans écran
 * accessible au rôle n'apparaît pas. À venir : Études (lot 3), Raccordement (lots 2 et 3), Suivi Rac (lot 5).
 */
export const NAV_GROUPS: NavGroup[] = [
  {
    href: "/avant-projet",
    label: "Avant-projet",
    description: "Les projets de déploiement, de l'avant-projet à l'exploitation.",
    items: [
      { href: "/projets", label: "Projets", description: "Un projet par plaque et sa phase.", roles: ["admin", "dg", "dt", "be_manager", "supervisor"] },
    ],
  },
  {
    href: "/deploiement",
    label: "Déploiement",
    description: "Le réseau : plaques, sous-plaques, splitters, PCO et OLT.",
    items: [
      { href: "/reseau", label: "Réseau", description: "Parcourir plaques, sous-plaques, splitters et PCO." },
      { href: "/plaques", label: "Plaques", description: "Ajouter ou corriger une plaque.", roles: ["admin"] },
      { href: "/sous-plaques", label: "Sous-plaques", description: "Ajouter ou corriger une sous-plaque.", roles: ["admin"] },
      { href: "/splitters", label: "Splitters", description: "Ajouter ou corriger un splitter (JDV).", roles: ["admin"] },
      { href: "/olt", label: "OLT", description: "Les équipements de tête.", roles: ["admin", "dg", "dt", "supervisor", "coordinator", "be_manager"] },
      { href: "/importer", label: "Importer le réseau", description: "Créer le réseau depuis un fichier CSV.", roles: ["admin"] },
    ],
  },
  {
    href: "/administration",
    label: "Administration",
    description: "Les équipes et les comptes.",
    items: [
      { href: "/equipes", label: "Équipes", description: "Coordinateur, chef d'équipe et membres.", roles: ["admin", "dg", "dt", "coordinator"] },
      { href: "/utilisateurs", label: "Utilisateurs", description: "Comptes, rôles et missions autorisées.", roles: ["admin", "dg", "dt"] },
    ],
  },
];

function visible(item: NavItem, role: Role): boolean {
  return !item.roles || item.roles.includes(role);
}

/** Les activités du rôle, avec seulement les écrans auxquels il a droit ; une activité vide disparaît. */
export function groupsFor(role: Role): NavGroup[] {
  return NAV_GROUPS.map((group) => ({ ...group, items: group.items.filter((item) => visible(item, role)) })).filter((group) => group.items.length > 0);
}

/** Tous les écrans du rôle, à plat. */
export function navFor(role: Role): NavItem[] {
  return [HOME, ...groupsFor(role).flatMap((group) => group.items)];
}

export function isActive(pathname: string, href: string): boolean {
  return href === "/" ? pathname === "/" : pathname === href || pathname.startsWith(`${href}/`);
}

/** Vrai quand la page courante est la page d'accueil de l'activité ou l'un de ses écrans. */
export function isGroupActive(pathname: string, group: NavGroup): boolean {
  return isActive(pathname, group.href) || group.items.some((item) => isActive(pathname, item.href));
}

export const BOTTOM_BAR_MAX = 4;

/**
 * Barre du bas sur téléphone : l'accueil et les activités (4 au plus), le reste dans « Plus ».
 * Chaque activité ouvre sa page d'accueil, qui liste ses écrans ; le tiroir « Plus » montre tout le menu.
 */
export function bottomBarFor(role: Role): { main: NavItem[]; groups: NavGroup[]; hasMore: boolean } {
  const groups = groupsFor(role);
  const entries: NavItem[] = [HOME, ...groups.map((group) => ({ href: group.href, label: group.label }))];
  const hasMore = entries.length > BOTTOM_BAR_MAX;

  return { main: hasMore ? entries.slice(0, BOTTOM_BAR_MAX) : entries, groups, hasMore };
}
