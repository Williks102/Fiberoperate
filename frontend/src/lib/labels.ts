import type { Tone } from "@/components/ui/badge";
import type { MissionType, OrderStatusValue, Pco, ProjectPhase, Role, ZoneStatus } from "@/lib/types";

export const ZONE_STATUS_LABELS: Record<ZoneStatus, string> = {
  planned: "Planifiée",
  deploying: "En déploiement",
  in_operation: "En exploitation",
  closed: "Clôturée",
};

export const VENDOR_LABELS = {
  huawei: "Huawei (hub)",
  nokia: "Nokia (SRO)",
} as const;

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

/** Phases d'un projet de déploiement, dans l'ordre du parcours. */
export const PROJECT_PHASES: { value: ProjectPhase; label: string }[] = [
  { value: "pre_project", label: "Avant-projet" },
  { value: "be_manager_review", label: "Validation du chef du BE" },
  { value: "dt_review", label: "Validation du DT" },
  { value: "client_review", label: "Validation du client" },
  { value: "implantation_survey", label: "Survey d'implantation" },
  { value: "deployment", label: "Déploiement" },
  { value: "as_built_survey", label: "Relevé final" },
  { value: "in_operation", label: "Exploitation" },
];

export type PhaseStep = { value: ProjectPhase; label: string; state: "done" | "current" | "todo" };

/** La frise des phases : celles déjà passées, la phase courante, celles à venir. */
export function phaseProgress(current: ProjectPhase): PhaseStep[] {
  const index = PROJECT_PHASES.findIndex((p) => p.value === current);

  return PROJECT_PHASES.map((p, i) => ({ ...p, state: i < index ? "done" : i === index ? "current" : "todo" }));
}

export const DECISION_LABELS = { approved: "Validé", rejected: "Refusé" } as const;

export const ROLE_LABELS: Record<Role, string> = {
  admin: "Administrateur",
  dg: "Directeur général",
  dt: "Directeur technique",
  supervisor: "Superviseur",
  coordinator: "Coordinateur",
  technician: "Technicien",
  be_manager: "Chef du bureau d'études",
};

export const MISSION_LABELS: Record<MissionType, string> = {
  deployment: "Déploiement",
  customer_connection: "Raccordement client",
  maintenance: "Maintenance",
  study: "Étude",
  drawing: "Dessin et avant-projet",
  survey: "Relevés (survey)",
};

/** Couleur d'état d'une phase de projet : validations en attente = attention, travaux = information, exploitation = succès. */
export const PHASE_TONES: Record<ProjectPhase, Tone> = {
  pre_project: "neutral",
  be_manager_review: "warning",
  dt_review: "warning",
  client_review: "warning",
  implantation_survey: "info",
  deployment: "info",
  as_built_survey: "info",
  in_operation: "success",
};

export const ZONE_TONES: Record<ZoneStatus, Tone> = { planned: "neutral", deploying: "info", in_operation: "success", closed: "neutral" };

/** Couleur d'état d'un PCO ; un libellé d'origine sans statut normalisé reste neutre. */
export function pcoTone(pco: Pick<Pco, "status" | "is_blocked">): Tone {
  if (pco.is_blocked) return "danger";
  return pco.status === "activated" ? "success" : pco.status === "spliced" || pco.status === "pulled" ? "info" : "neutral";
}

/** Couleur d'état d'une commande : en cours = information, reporté = attention, échec = danger, terminé = succès. */
export const ORDER_TONES: Record<OrderStatusValue, Tone> = {
  received: "neutral",
  in_study: "info",
  studied: "info",
  appointment_set: "info",
  installed: "success",
  postponed: "warning",
  failed: "danger",
  activated: "success",
};

export const STUDY_RESULT_LABELS = { feasible: "Raccordable", not_feasible: "Non raccordable", customer_absent: "Client absent" } as const;
