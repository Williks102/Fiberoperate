import { BookOpenText, Boxes, CircleCheck, ClipboardList, FileUp, Hourglass, FolderKanban, House, LayoutGrid, Map, Network, Package, RadioTower, Server, Split, UserCog, Users, type LucideIcon } from "lucide-react";
import type { NavIconName } from "@/lib/navigation";

/** Une icône par activité. Les activités à venir (Études, Raccordement, Suivi Rac) ajouteront la leur. */
export const NAV_ICONS: Record<NavIconName, LucideIcon> = {
  home: House,
  project: FolderKanban,
  network: Network,
  customer: ClipboardList,
  admin: Users,
};

/** Une icône par écran du menu, pour les repérer d'un coup d'œil dans la barre latérale et le tiroir. */
const ITEM_ICONS: Record<string, LucideIcon> = {
  "/projets": FolderKanban,
  "/catalogue": BookOpenText,
  "/plaques": Map,
  "/sous-plaques": LayoutGrid,
  "/sro": RadioTower,
  "/splitters": Split,
  "/pco": Package,
  "/olt": Server,
  "/clients/importer": FileUp,
  "/instances": Hourglass,
  "/raccordables": ClipboardList,
  "/suivi-raccordement": CircleCheck,
  "/equipes": Users,
  "/utilisateurs": UserCog,
};

export function ItemIcon({ href, className = "h-5 w-5" }: { href: string; className?: string }) {
  const Icon = ITEM_ICONS[href] ?? Boxes;

  return <Icon aria-hidden="true" className={className} strokeWidth={2} />;
}

export function NavIcon({ name, className = "h-6 w-6" }: { name: NavIconName; className?: string }) {
  const Icon = NAV_ICONS[name];

  return <Icon aria-hidden="true" className={className} strokeWidth={2} />;
}
