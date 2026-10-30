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

export interface Project {
  id: string;
  plaque_id: string;
  plaque: { id: string; name: string } | null;
  phase: ProjectPhase;
  phase_label: string;
  phase_changed_at: string | null;
  /** Présent sur la fiche d'un projet seulement. */
  history?: ProjectHistoryLine[];
}

export interface Olt {
  id: string;
  name: string;
  notes: string | null;
}
