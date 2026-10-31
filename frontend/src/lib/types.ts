export type Role = "admin" | "dg" | "dt" | "supervisor" | "coordinator" | "technician" | "be_manager";

/** Reflet de App\Http\Resources\UserResource. */
export interface User {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  role: Role;
  role_label: string;
  manager_id: string | null;
  is_active: boolean;
  skills?: MissionType[];
}

export interface Paged<T> {
  data: T[];
  meta: { current_page: number; last_page: number; total: number };
}

export interface Plaque {
  id: string;
  name: string;
  olt_id: string | null;
  supervisor_id: string | null;
  olt?: { id: string; name: string } | null;
  supervisor?: { id: string; name: string } | null;
}

export type ZoneStatus = "planned" | "deploying" | "in_operation" | "closed";
export type PcoStatus = "to_pull" | "pulled" | "spliced" | "activated";

export interface Zone {
  id: string;
  plaque_id: string;
  code: string;
  name: string | null;
  vendor: "huawei" | "nokia" | null;
  /** « Hub » (Huawei) ou « SRO » (Nokia) ; null si le fabricant est inconnu. */
  access_point_label: string | null;
  status: ZoneStatus;
  plaque?: { id: string; name: string } | null;
}

export interface Jdv {
  id: string;
  zone_id: string;
  code: string;
  zone?: { id: string; code: string; plaque: { id: string; name: string } | null };
}

export interface Sro {
  id: string;
  zone_id: string;
  code: string;
  latitude: string | null;
  longitude: string | null;
  olt_slot: string | null;
  olt_port: string | null;
  fiber_strand: string | null;
  zone?: { id: string; code: string; plaque: { id: string; name: string } | null };
}

export interface Pco {
  id: string;
  zone_id: string;
  jdv_id: string;
  code: string;
  status: PcoStatus | null;
  status_raw: string | null;
  is_blocked: boolean;
  latitude?: string | null;
  longitude?: string | null;
  measured_latitude?: string | null;
  measured_longitude?: string | null;
  measured_at?: string | null;
  measured_by?: { id: string; name: string } | null;
  /** Écart en mètres entre la position prévue et la position mesurée. */
  deviation_m?: number | null;
  olt_slot?: string | null;
  olt_port?: string | null;
  fiber_strand?: string | null;
  comment?: string | null;
  jdv?: { id: string; code: string } | null;
  zone?: { id: string; code: string; plaque: { id: string; name: string } | null } | null;
}

export type MissionType = "deployment" | "customer_connection" | "maintenance" | "study" | "drawing" | "survey";

export interface Team {
  id: string;
  name: string;
  leader_id: string | null;
  coordinator_id: string | null;
  leader: { id: string; name: string } | null;
  coordinator: { id: string; name: string } | null;
  members: { id: string; name: string }[];
  member_ids: string[];
}

export type ProjectPhase =
  | "pre_project"
  | "be_manager_review"
  | "dt_review"
  | "client_review"
  | "implantation_survey"
  | "deployment"
  | "as_built_survey"
  | "in_operation";

export interface ProjectHistoryLine {
  id: string;
  from_phase: ProjectPhase | null;
  from_phase_label: string | null;
  to_phase: ProjectPhase;
  to_phase_label: string;
  decision: "approved" | "rejected" | null;
  reason: string | null;
  author: { id: string; name: string } | null;
  created_at: string | null;
}

export interface ProjectNetwork {
  olt: { id: string; name: string } | null;
  supervisor: { id: string; name: string } | null;
  zones: {
    id: string;
    code: string;
    status: ZoneStatus;
    sro: string | null;
    splitters: number;
    pcos: number;
    /** Avancement des PCO du plan : par statut, et positions relevées sur le terrain. */
    progress: { to_pull: number; pulled: number; spliced: number; activated: number; measured: number };
  }[];
}

export interface Project {
  id: string;
  /** Déploiement d'une plaque, ou désaturation (nouveau projet sur une plaque déjà déployée). */
  kind: "deployment" | "desaturation";
  kind_label: string;
  plaque_id: string;
  plaque: { id: string; name: string } | null;
  phase: ProjectPhase;
  phase_label: string;
  phase_changed_at: string | null;
  /** Présents sur la fiche d'un projet seulement. */
  history?: ProjectHistoryLine[];
  /** Actions du circuit de validation permises à l'utilisateur dans la phase courante. */
  actions?: { submit: boolean; decide: boolean; advance: boolean; advance_to: string | null };
  network?: ProjectNetwork | null;
}

export interface Olt {
  id: string;
  name: string;
  notes: string | null;
}

export type OrderStatusValue = "received" | "in_study" | "studied" | "appointment_set" | "installed" | "postponed" | "failed" | "activated";

export interface CustomerOrder {
  id: string;
  customer_ref: string;
  account_number: string | null;
  ft_line: string | null;
  name: string;
  phone_1: string | null;
  phone_2: string | null;
  address_raw: string | null;
  declared_latitude: string | null;
  declared_longitude: string | null;
  distance_m: number | null;
  status: { value: OrderStatusValue; label: string };
  stage: "instances" | "raccordables" | "raccordes";
  study_result: "feasible" | "not_feasible" | "customer_absent" | null;
  initial_study: "done" | "to_do" | null;
  initial_study_raw: string | null;
  registered_at: string | null;
  sent_to_contractor_at: string | null;
  operator_line_status: string | null;
  observation: string | null;
  zone?: { id: string; code: string } | null;
  pco?: { id: string; code: string } | null;
  interventions?: { id: string; technician_id: string; type: string; status: string; status_label: string; planned_date: string; technician: string | null; observation: string | null }[];
  studies?: {
    id: string;
    result: "feasible" | "not_feasible" | "customer_absent";
    result_label: string;
    concrete_poles_existing: number;
    concrete_poles_to_install: number;
    metal_poles_existing: number;
    metal_poles_to_install: number;
    distance_m: number | null;
    problem: string | null;
    field_comments: string | null;
    internal_comments?: string | null;
    at: string | null;
  }[];
  history?: { from: OrderStatusValue | null; to: OrderStatusValue; to_label: string; observation: string | null; author: string | null; at: string | null }[];
}
