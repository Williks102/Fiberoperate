export type Role = "admin" | "dg" | "dt" | "supervisor" | "coordinator" | "technician";

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
}

export interface Jdv {
  id: string;
  zone_id: string;
  code: string;
}

export interface Pco {
  id: string;
  zone_id: string;
  jdv_id: string;
  code: string;
  status: PcoStatus | null;
  status_raw: string | null;
  is_blocked: boolean;
}

export type MissionType = "deployment" | "customer_connection" | "maintenance" | "study";

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
