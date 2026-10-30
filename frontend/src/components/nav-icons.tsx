import { FolderKanban, House, Network, Users, type LucideIcon } from "lucide-react";
import type { NavIconName } from "@/lib/navigation";

/** Une icône par activité. Les activités à venir (Études, Raccordement, Suivi Rac) ajouteront la leur. */
export const NAV_ICONS: Record<NavIconName, LucideIcon> = {
  home: House,
  project: FolderKanban,
  network: Network,
  admin: Users,
};

export function NavIcon({ name, className = "h-6 w-6" }: { name: NavIconName; className?: string }) {
  const Icon = NAV_ICONS[name];

  return <Icon aria-hidden="true" className={className} strokeWidth={2} />;
}
