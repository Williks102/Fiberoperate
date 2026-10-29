import type { MissionType, Pco, Role, ZoneStatus } from "@/lib/types";

export const ZONE_STATUS_LABELS: Record<ZoneStatus, string> = {
  planned: "Planifiée",
  deploying: "En déploiement",
  in_operation: "En exploitation",
  closed: "Clôturée",
};

export const PCO_STATUS_LABELS = {
  to_pull: "À tirer",
  pulled: "Tiré",
  spliced: "Soudure faite",
  activated: "Activé",
} as const;

/**
 * Libellé d'un PCO. Tant que la correspondance des statuts n'est pas validée, le libellé
 * d'origine du fichier de suivi (status_raw) est affiché tel quel.
 */
export function pcoStatusLabel(pco: Pick<Pco, "status" | "status_raw">): string {
  if (pco.status) return PCO_STATUS_LABELS[pco.status];
  return pco.status_raw?.trim() || "Non renseigné";
}

export const ROLE_LABELS: Record<Role, string> = {
  admin: "Administrateur",
  dg: "Directeur général",
  dt: "Directeur technique",
  supervisor: "Superviseur",
  coordinator: "Coordinateur",
  technician: "Technicien",
};

export const MISSION_LABELS: Record<MissionType, string> = {
  deployment: "Déploiement",
  customer_connection: "Raccordement client",
  maintenance: "Maintenance",
  study: "Étude",
};
